<?php

declare(strict_types=1);

namespace QUITests\Permalinks\Integration;

use Doctrine\DBAL\Schema\Table;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use QUI;
use QUI\Permalinks\Permalink;
use QUI\Projects\Manager as ProjectManager;
use QUI\Projects\Project;
use QUI\Projects\Site;

class PermalinkLifecycleTest extends TestCase
{
    private const PROJECT_NAME = 'phpunit_permalinks';
    private const LANGUAGE = 'de';

    private Project&MockObject $Project;
    private Site\Edit&MockObject $RootSite;
    private Site\Edit&MockObject $FirstSite;
    private Site\Edit&MockObject $SecondSite;
    private string $table;

    /** @var array<string, mixed> */
    private array $firstSiteAttributes = [];

    protected function setUp(): void
    {
        $this->Project = $this->createMock(Project::class);
        $this->Project->method('getName')->willReturn(self::PROJECT_NAME);
        $this->Project->method('getLang')->willReturn(self::LANGUAGE);
        $this->Project->method('getAttribute')->willReturnCallback(
            static fn(string $name): string => match ($name) {
                'name' => self::PROJECT_NAME,
                'lang' => self::LANGUAGE,
                default => ''
            }
        );

        $this->RootSite = $this->createSite(1);
        $this->FirstSite = $this->createSite(2, $this->firstSiteAttributes);
        $this->SecondSite = $this->createSite(3);

        $this->Project->method('firstChild')->willReturn($this->RootSite);
        $this->Project->method('get')->willReturnCallback(
            fn(int $id): Site\Edit => match ($id) {
                1 => $this->RootSite,
                2 => $this->FirstSite,
                3 => $this->SecondSite,
                default => throw new QUI\Exception('Test site not found', 404)
            }
        );

        ProjectManager::$projects[self::PROJECT_NAME][self::LANGUAGE] = $this->Project;

        $this->table = QUI::getDBProjectTableName('permalinks', $this->Project, false);
        $this->createPermalinksTable();
        QUI::getDataBaseConnection()->delete($this->table, []);
    }

    protected function tearDown(): void
    {
        QUI::getDataBaseConnection()->delete($this->table, []);
        unset(ProjectManager::$projects[self::PROJECT_NAME]);
        $this->firstSiteAttributes = [];
    }

    public static function tearDownAfterClass(): void
    {
        $table = QUI_DB_PRFX . self::PROJECT_NAME . '_permalinks';
        $SchemaManager = QUI::getSchemaManager();

        if ($SchemaManager->tablesExist([$table])) {
            $SchemaManager->dropTable($table);
        }

        unset(ProjectManager::$projects[self::PROJECT_NAME]);
    }

    public function testPermalinkCanBeCreatedResolvedAndDeleted(): void
    {
        self::assertTrue(Permalink::setPermalinkForSite($this->FirstSite, 'permalink-target'));
        self::assertSame('permalink-target', Permalink::getPermalinkForSite($this->FirstSite));

        $ResolvedSite = Permalink::getSiteByPermalink($this->Project, 'permalink-target');
        self::assertSame($this->FirstSite, $ResolvedSite);

        Permalink::deletePermalinkForSite($this->FirstSite);

        $this->expectException(QUI\Exception::class);
        $this->expectExceptionCode(404);
        Permalink::getPermalinkForSite($this->FirstSite);
    }

    public function testFirstProjectSiteCannotReceivePermalink(): void
    {
        $this->expectException(QUI\Exception::class);
        Permalink::setPermalinkForSite($this->RootSite, 'root-link');
    }

    public function testSiteCannotReceiveSecondPermalink(): void
    {
        Permalink::setPermalinkForSite($this->FirstSite, 'first-link');

        try {
            Permalink::setPermalinkForSite($this->FirstSite, 'second-link');
            self::fail('A site with a permalink must reject a second permalink.');
        } catch (QUI\Exception $Exception) {
            self::assertSame(409, $Exception->getCode());
        }

        self::assertSame('first-link', Permalink::getPermalinkForSite($this->FirstSite));
    }

    public function testPermalinkCannotBeAssignedToSecondSite(): void
    {
        Permalink::setPermalinkForSite($this->FirstSite, 'unique-link');

        try {
            Permalink::setPermalinkForSite($this->SecondSite, 'unique-link');
            self::fail('A permalink must not be assigned to two sites.');
        } catch (QUI\Exception $Exception) {
            self::assertSame(409, $Exception->getCode());
        }
    }

    public function testUrlParametersResolvePermalinkWithDefaultSuffix(): void
    {
        $link = 'parameterized-link' . QUI\Rewrite::getDefaultSuffix();

        QUI::getDataBaseConnection()->insert($this->table, [
            'id' => $this->FirstSite->getId(),
            'lang' => $this->Project->getLang(),
            'link' => $link
        ]);

        $ResolvedSite = Permalink::getSiteByPermalink($this->Project, 'parameterized-link_value');
        self::assertSame($this->FirstSite, $ResolvedSite);
    }

    public function testUnknownPermalinkThrowsNotFoundException(): void
    {
        $this->expectException(QUI\Exception::class);
        $this->expectExceptionCode(404);
        Permalink::getSiteByPermalink($this->Project, 'missing-link');
    }

    public function testSaveEventsNormalizeAndPersistPermalink(): void
    {
        $this->FirstSite->setAttribute('quiqqer.permalinks.site.permalink', 'hello--world._/path');
        Permalink::onSiteSaveBefore($this->FirstSite);

        self::assertSame(
            'hello-world/path',
            $this->FirstSite->getAttribute('quiqqer.permalinks.site.permalink')
        );

        Permalink::onSave($this->FirstSite);
        self::assertSame('hello-world/path', Permalink::getPermalinkForSite($this->FirstSite));

        Permalink::onSave($this->FirstSite);
        self::assertSame('hello-world/path', Permalink::getPermalinkForSite($this->FirstSite));
    }

    public function testSaveEventIgnoresEmptyPermalink(): void
    {
        Permalink::onSave($this->FirstSite);

        $this->expectException(QUI\Exception::class);
        $this->expectExceptionCode(404);
        Permalink::getPermalinkForSite($this->FirstSite);
    }

    public function testLoadAndRewriteEventsExposeStoredPermalink(): void
    {
        Permalink::setPermalinkForSite($this->FirstSite, 'event-link');

        Permalink::onSiteLoad($this->FirstSite);

        self::assertSame('event-link', $this->FirstSite->getAttribute('quiqqer.permalinks.site.permalink'));
        self::assertSame('event-link', $this->FirstSite->getAttribute('canonical'));

        $url = 'original-url';
        Permalink::onUrlRewritten($this->FirstSite, $url);
        self::assertSame('event-link', $url);
    }

    public function testMissingPermalinkLeavesLoadAndRewriteValuesUntouched(): void
    {
        Permalink::onSiteLoad($this->FirstSite);

        $url = 'original-url';
        Permalink::onUrlRewritten($this->FirstSite, $url);

        self::assertSame([], $this->firstSiteAttributes);
        self::assertSame('original-url', $url);
    }

    public function testRequestEventSelectsSiteAndIgnoresIrrelevantUrls(): void
    {
        Permalink::setPermalinkForSite($this->FirstSite, 'request-link');

        $Rewrite = $this->createMock(QUI\Rewrite::class);
        $Rewrite->expects(self::once())->method('getProject')->willReturn($this->Project);
        $Rewrite->expects(self::once())->method('setSite')->with($this->FirstSite);

        Permalink::onRequest($Rewrite, '');
        Permalink::onRequest($Rewrite, 'media/cache/image.jpg');
        Permalink::onRequest($Rewrite, 'request-link');
    }

    public function testUnknownRequestDoesNotSelectSite(): void
    {
        $Rewrite = $this->createMock(QUI\Rewrite::class);
        $Rewrite->expects(self::once())->method('getProject')->willReturn($this->Project);
        $Rewrite->expects(self::never())->method('setSite');

        Permalink::onRequest($Rewrite, 'missing-link');
    }

    public function testUrlNormalizationKeepsPathSeparators(): void
    {
        self::assertSame('hello-world/path', Permalink::clearPermaLinkUrl('hello--world._/path'));
        self::assertSame(
            'hello-world/path',
            Permalink::clearPermaLinkUrl('hello--world._/path', $this->Project)
        );
    }

    /**
     * @param array<string, mixed>|null $attributes
     */
    private function createSite(int $id, ?array &$attributes = null): Site\Edit&MockObject
    {
        $attributes ??= [];
        $Site = $this->createMock(Site\Edit::class);
        $Site->method('getId')->willReturn($id);
        $Site->method('getProject')->willReturn($this->Project);
        $Site->method('getAttribute')->willReturnCallback(
            static function (string $name) use (&$attributes): mixed {
                return $attributes[$name] ?? false;
            }
        );
        $Site->method('setAttribute')->willReturnCallback(
            static function (string $name, mixed $value) use (&$attributes): void {
                $attributes[$name] = $value;
            }
        );

        return $Site;
    }

    private function createPermalinksTable(): void
    {
        $SchemaManager = QUI::getSchemaManager();

        if ($SchemaManager->tablesExist([$this->table])) {
            return;
        }

        $Table = new Table($this->table);
        $Table->addColumn('id', 'bigint');
        $Table->addColumn('lang', 'string', ['length' => 2]);
        $Table->addColumn('link', 'text', ['notnull' => false]);
        $Table->setPrimaryKey(['id', 'lang']);
        $SchemaManager->createTable($Table);
    }
}
