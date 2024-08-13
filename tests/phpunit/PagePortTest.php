<?php

use JsonSchema\Validator;

/**
 * @coversDefaultClass PagePort
 * @group Database
 * @group extension-PagePort
 */
class PagePortTest extends MediaWikiIntegrationTestCase {

	/** @var PagePort */
	private $pp;

	public static function setupBeforeClass(): void {
		parent::setUpBeforeClass();
		define( "NS_CUSTOM", 4000 );
		define( "NS_CUSTOM_TALK", 4001 );
		define( "NS_CUSTOM_SLASH", 4002 );
		define( "NS_CUSTOM_SLASH_TALK", 4003 );
	}

	public function setup(): void {
		parent::setup();

		$this->setMwGlobals(
			[
				'wgExtraNamespaces' => [
					NS_CUSTOM => "CustomNamespace",
					NS_CUSTOM_TALK => "CustomNamespace_talk",
					NS_CUSTOM_SLASH => "CustomNamespace/With/Slashes",
					NS_CUSTOM_SLASH_TALK => "CustomNamespace/With/Slashes_talk",
				],
				'wgMetaNamespace' => 'RandomMetaName'
			]
		);

		$this->insertPage( 'Page1Test', 'Page1TestContents' );
		$this->insertPage( 'Page2Test', 'Page2TestContents ===Header===' );
		$this->insertPage( 'Page3Test', 'Page3TestContents {{!}}' );
		$this->insertPage( 'Page4Test/SubPage1Test', 'SubPage1TestContents' );
		$this->insertPage( 'Page5Test', 'Page5TestContents', NS_TEMPLATE );
		$this->insertPage( 'Page6Test', 'Page6TestContents', NS_FILE );
		$this->insertPage( 'Page with spaces', 'Page with spaces test contents' );
		$this->insertPage( 'Page7Test', 'Page7TestContents', NS_CUSTOM );
		$this->insertPage( 'Page8Test', 'Page8TestContents', NS_CUSTOM_SLASH );
		$this->insertPage( 'Page9Test', 'Page9TestContents [[Category:TestRootCategory]]', NS_CATEGORY );
		$this->insertPage( 'Page10Test', 'Page10TestContents', NS_PROJECT );
		$this->pp = PagePort::getInstance();
	}

	/**
	 * @covers PagePort::getAllPagesForCategory
	 */
	public function testExportCategories(): void {
		$pages = $this->pp->getAllPagesForCategory( 'TestRootCategory', 1, null, true );
		$this->assertArrayEquals(
			[
				'Category:Page9Test'
			],
			$pages
		);
		$pages = $this->pp->getAllPagesForCategory( 'TestRootCategory', 1, null, false );
		$this->assertArrayEquals(
			[],
			$pages
		);
	}

	/**
	 * @covers PagePort::export
	 */
	public function testExportPages(): void {
		$pages = [
			'Page1Test',
			'Page2Test',
			'Page3Test',
			'Page4Test/SubPage1Test',
			'Template:Page5Test',
			'File:Page6Test',
			'Page with spaces',
			'CustomNamespace:Page7Test',
			'CustomNamespace/With/Slashes:Page8Test',
			'Project:Page10Test',
			'RandomMetaName:Page10Test',
		];
		$result = $this->pp->export( $pages, "testRoot", false );
		$this->assertCount( count( $pages ) - 1, $result, 'with $save=false an array is returned' );
		$this->assertEquals(
			'testRoot/Main/Page1Test.mediawiki',
			array_keys( $result )[0],
			'file root is calculated correctly'
		);
		$this->assertEquals(
			'Page1TestContents',
			$result[array_keys( $result )[0]],
			'page contents is exported correctly'
		);
		$this->assertEquals(
			'Page2TestContents ===Header===',
			$result[array_keys( $result )[1]],
			'page contents with wiki markup is exported correctly'
		);
		$this->assertEquals(
			'Page3TestContents {{!}}',
			$result[array_keys( $result )[2]],
			'page contents with wiki templates are exported correctly'
		);
		$this->assertEquals(
			'testRoot/Main/Page4Test|SubPage1Test.mediawiki',
			array_keys( $result )[3],
			'file root for subpages is calculated correctly'
		);
		$this->assertEquals(
			'testRoot/Template/Page5Test.mediawiki',
			array_keys( $result )[4],
			'file root for namespaces is calculated correctly'
		);
		$this->assertEquals(
			'testRoot/File/Page6Test.mediawiki',
			array_keys( $result )[5],
			'file root for namespaces is calculated correctly'
		);
		$this->assertEquals(
			'testRoot/Main/Page with spaces.mediawiki',
			array_keys( $result )[6],
			'file root for pages with spaces is calculated correctly'
		);
		$this->assertEquals(
			'testRoot/CustomNamespace/Page7Test.mediawiki',
			array_keys( $result )[7],
			'file root for pages with extra namespace is calculated correctly'
		);
		$this->assertEquals(
			'testRoot/CustomNamespace|With|Slashes/Page8Test.mediawiki',
			array_keys( $result )[8],
			'file root for pages with extra namespace with slashes is calculated correctly'
		);
		$this->assertEquals(
			'testRoot/Project/Page10Test.mediawiki',
			array_keys( $result )[9],
			'file root for project/meta pages exported correctly'
		);
	}

	/**
	 * @covers PagePort::exportJSON
	 */
	public function testExportPagesJSON(): void {
		$pages = [
			'Page1Test',
			'Page2Test',
			'Page3Test',
			'Page4Test/SubPage1Test',
			'Template:Page5Test',
			'File:Page6Test',
			'Page with spaces',
			'CustomNamespace:Page7Test',
			'CustomNamespace/With/Slashes:Page8Test',
			'CustomNamespace:Page7Test',
			'CustomNamespace/With/Slashes:Page8Test',
			'Project:Page10Test',
			'RandomMetaName:Page10Test',
		];
		$result = $this->pp->exportJSON(
			$pages,
			"testRoot",
			"testPackage",
			"testDesc",
			"testRepo",
			"testVersion",
			"testAuthor",
			"testPublisher",
			[ "testpackage1", "testpackage2" ],
			[ "testextension1", "testextension2" ],
			false
		);
		$this->assertCount( 2, $result, 'with $save=false an array of two items is returned' );
		$json = json_decode( $result[1] );
		$this->assertJsonMatchesSchema( $json, __DIR__ . '/../../pageexchange.schema.json' );
		// Also test some of the JSON data
		$json = json_decode( $result[1], true );
		// NS_IMAGE -> NS_FILE
		$this->assertEquals(
			'NS_FILE',
			$json['packages']['testPackage']['pages'][5]['namespace']
		);
		// Repo url properly escaped
		$this->assertEquals(
			'https://raw.githubusercontent.com/testRepo/master/Main%2FPage1Test.mediawiki',
			$json['packages']['testPackage']['pages'][0]['url']
		);
		// Required extensions
		$this->assertEquals(
			'testextension1',
			$json['packages']['testPackage']['requiredExtensions'][0]
		);
		$this->assertEquals(
			'testextension2',
			$json['packages']['testPackage']['requiredExtensions'][1]
		);
		// Required packages
		$this->assertEquals(
			'testpackage1',
			$json['packages']['testPackage']['requiredPackages'][0]
		);
		$this->assertEquals(
			'testpackage2',
			$json['packages']['testPackage']['requiredPackages'][1]
		);
		// ID generation
		$this->assertEquals(
			'testPackage',
			$json['packages']['testPackage']['globalID']
		);
		// Meta namespace
		$this->assertEquals(
			'NS_PROJECT',
			$json['packages']['testPackage']['pages'][12]['namespace']
		);
		$this->assertEquals(
			'https://raw.githubusercontent.com/testRepo/master/Project%2FPage10Test.mediawiki',
			$json['packages']['testPackage']['pages'][12]['url']
		);
	}

	public function namespaceNames(): array {
		return [
			[ NS_MAIN, 'Main' ],
			[ NS_FILE, 'File' ],
			[ NS_TEMPLATE, 'Template' ],
			[ NS_PROJECT, 'Project' ],
			[ NS_MEDIAWIKI, 'MediaWiki' ],
			[ 4000, 'CustomNamespace' ]
		];
	}

	/**
	 * @covers       PagePort::getNamespaceName
	 * @dataProvider namespaceNames
	 */
	public function testGetNamespaceName( $input, $expected ): void {
		$this->assertEquals( $expected, $this->pp->getNamespaceName( $input ) );
	}

	public function namespaceValues(): array {
		return [
			[ 0, 'NS_MAIN' ],
			[ 6, 'NS_FILE' ],
			[ 10, 'NS_TEMPLATE' ],
			[ 4, 'NS_PROJECT' ],
			[ 8, 'NS_MEDIAWIKI' ],
			[ 4000, 'NS_CUSTOM' ],
			[ 4002, 'NS_CUSTOM_SLASH' ]
		];
	}

	/**
	 * @covers       PagePort::getNamespaceByValue
	 * @dataProvider namespaceValues
	 */
	public function testGetNamespaceByValue( $input, $expected ) {
		$this->assertEquals( $expected, $this->pp->getNamespaceByValue( $input ) );
	}

	/**
	 * @covers PagePort::import
	 * @covers PagePort::getAllPages
	 * @covers PagePort::delete
	 */
	public function testImport() {
		$tempDir = $this->tempdir( 'pageprot_' );
		$pages = [
			'Page1Test',
			'Page2Test',
			'Page3Test',
			'Page4Test/SubPage1Test',
			'Template:Page5Test',
			'File:Page6Test',
			'Page with spaces'
		];
		$this->pp->export( $pages, $tempDir );
		foreach ( $pages as $page ) {
			$title = Title::newFromText( $page );
			$wp = WikiPage::factory( $title );
			$wp->doDeleteArticleReal( 'test', $this->getTestUser( 'sysop' )->getUser(), true );
		}
		$this->pp->import( $tempDir );
		foreach ( $pages as $page ) {
			$title = Title::newFromText( $page );
			$this->assertTrue( $title->exists() );
			$wp = WikiPage::factory( $title );
			$this->assertTrue( strlen( $wp->getContent()->getWikitextForTransclusion() ) > 0 );
		}
		$allPages = $this->pp->getAllPages();
		foreach ( $pages as $p ) {
			$this->assertContains( $p, $allPages );
		}
		$this->pp->delete( $tempDir );
		$allPages = $this->pp->getAllPages();
		foreach ( $pages as $p ) {
			$this->assertNotContains( $p, $allPages );
		}
	}

	/**
	 * Creates temp directory
	 *
	 * @param string $prefix
	 *
	 * @return false|string
	 */
	private function tempdir( $prefix = 'php' ) {
		$tempfile = tempnam( '', '' );
		if ( file_exists( $tempfile ) ) {
			unlink( $tempfile );
		}
		mkdir( $tempfile );
		if ( is_dir( $tempfile ) ) {
			return $tempfile;
		}
	}

	/**
	 * MediaWiki's composer merge plugin does not merge dev dependencies, so
	 * rather than using the assertJsonMatchesSchema() from the
	 * estahn/phpunit-json-assertions library, re-implement it based on
	 * justinrainbow/json-schema
	 *
	 * @param mixed $json
	 * @param string $schema
	 */
	private function assertJsonMatchesSchema( $json, string $schema ) {
		$validator = new Validator();
		$validator->validate(
			$json,
			(object)[ '$ref' => 'file://' . realpath( $schema ) ]
		);
		if ( $validator->isValid() ) {
			$this->addToAssertionCount( 1 );
			return;
		}
		// For a more informative error message
		$this->assertSame( [], $validator->getErrors() );
	}

}
