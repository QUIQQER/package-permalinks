/**
 * Permalink input control (for a site panel)
 *
 * @module package/quiqqer/permalinks/bin/Input
 * @author www.pcsg.de (Henning Leutz)
 * @author www.pcsg.de (Jan Wennrich)
 */
define('package/quiqqer/permalinks/bin/Input', [

    'qui/QUI',
    'qui/controls/Control',
    'qui/controls/buttons/Button',
    'qui/controls/windows/Confirm',
    'qui/controls/messages/Information',

    'utils/Controls',

    'Ajax',
    'Locale',

    'css!package/quiqqer/permalinks/bin/Input.css'

], function (QUI, QUIControl, QUIButton, QUIConfirm, QUIInformation, QUIControlUtils, Ajax, Locale) {
    "use strict";

    var lg = 'quiqqer/permalinks';

    return new Class({

        Type   : 'package/quiqqer/permalinks/bin/Input',
        Extends: QUIControl,

        Binds: [
            'deletePermalink',
            '$onImport',
            '$onSiteSave'
        ],

        initialize: function (options) {
            this.parent(options);

            this.$Input        = null;
            this.$DeleteButton = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event : on import
         */
        $onImport: function () {
            var Container = new Element('div', {
                'data-quiid': this.getId(),
                'class'     : 'field-container-field field-container-field-no-padding',
                styles      : {
                    display: 'flex'
                }
            });

            this.$Input = this.$Elm.clone();
            this.$Input.inject(Container);

            this.$Input.addClass('permalink-input');

            // delete button
            this.$DeleteButton = new QUIButton({
                text    : Locale.get(lg, 'button.delete.text'),
                disabled: true,
                styles  : {
                    borderRadius: 0,
                    'float'     : 'none'
                },
                events  : {
                    onClick: this.deletePermalink
                }
            }).inject(Container);

            if (this.$Input.value !== '') {
                this.$Input.disabled = true;
                this.$DeleteButton.enable();
            }

            QUIControlUtils.getControlByElement(this.$Elm.getParent('.qui-panel')).then((SitePanel) => {
                if (SitePanel) {
                    SitePanel.getSite().addEvent('onSave', this.$onSiteSave);
                }
            });

            Container.replaces(this.$Elm);

            this.$Elm = Container;

            // id 1 cant have a permalink
            var PanelElm = this.$Elm.getParent('.qui-panel'),
                Panel    = QUI.Controls.getById(PanelElm.get('data-quiid')),
                Site     = Panel.getSite();

            if (Site.getId() === 1) {
                this.$Input.disabled = true;

                new QUIInformation({
                    message: Locale.get(lg, 'exception.firstChild.cant.have.permalink'),
                    styles : {
                        marginBottom: 10
                    }
                }).inject(this.$Elm);
            }
        },

        /**
         * Event: onSiteSave
         */
        $onSiteSave: function () {
            if (this.$Input.value !== '') {
                this.$Input.disabled = true;
                this.$DeleteButton.enable();
            } else {
                this.$Input.disabled = false;
                this.$DeleteButton.disable();
            }
        },

        /**
         * Delete the permalink
         */
        deletePermalink: function () {
            if (this.$Input.value === '') {
                return;
            }

            var self     = this,
                PanelElm = this.$Elm.getParent('.qui-panel'),
                Panel    = QUI.Controls.getById(PanelElm.get('data-quiid')),

                Site     = Panel.getSite(),
                Project  = Site.getProject();

            new QUIConfirm({
                title    : Locale.get(lg, 'window.delete.title'),
                maxHeight: 300,
                maxWidth : 500,
                autoclose: false,
                text     : Locale.get(lg, 'window.delete.text', {
                    id: Site.getId()
                }),
                events   : {
                    onOpen  : function () {
                        Panel.Loader.show();
                    },
                    onSubmit: function (Win) {
                        Win.Loader.show();

                        Ajax.post('package_quiqqer_permalinks_ajax_delete', function (result) {
                            self.$Input.value = result;

                            if (self.$Input.value === '') {
                                self.$Input.disabled = false;
                                self.$DeleteButton.disable();
                            }

                            Win.close();
                            Panel.Loader.hide();
                        }, {
                            project  : Project.getName(),
                            lang     : Project.getLang(),
                            id       : Site.getId(),
                            'package': 'quiqqer/permalinks',
                            onError  : function () {
                                Panel.Loader.hide();
                            }
                        });
                    },
                    onCancel: function () {
                        Panel.Loader.hide();
                    }
                }
            }).open();
        }
    });
});
