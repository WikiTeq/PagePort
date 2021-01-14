<?php

use EnricoStahn\JsonAssert\Assert as JsonAssert;

/**
 * @coversDefaultClass PagePort
 * @group Database
 */
class PagePortTest extends MediaWikiTestCase {

	use JsonAssert;

	/** @var PagePort */
	private $pp;

	public function setup(): void {
		parent::setup();
		$this->insertPage( 'Page1Test', 'Page1TestContents' );
		$this->insertPage( 'Page2Test', 'Page2TestContents ===Header===' );
		$this->insertPage( 'Page3Test', 'Page3TestContents {{!}}' );
		$this->insertPage( 'Page4Test/SubPage1Test', 'SubPage1TestContents' );
		$this->insertPage( 'Page5Test', 'Page5TestContents', NS_TEMPLATE );
		$this->insertPage( 'Page6Test', 'Page6TestContents', NS_FILE );
		$this->pp = PagePort::getInstance();
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
			'File:Page6Test'
		];
		$result = $this->pp->export( $pages, "testRoot", false );
		$this->assertCount( count( $pages ), $result, 'with $save=false an array is returned' );
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
			'File:Page6Test'
		];
		$result =
			$this->pp->exportJSON(
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
	}

	public function namespaceNames(): array {
		return [
			[ NS_MAIN, 'Main' ],
			[ NS_FILE, 'File' ],
			[ NS_TEMPLATE, 'Template' ],
			[ NS_PROJECT, 'Project' ],
			[ NS_MEDIAWIKI, 'MediaWiki' ]
		];
	}

	/**
	 * @covers       PagePort::getNamespaceName
	 * @dataProvider namespaceNames
	 */
	public function testGetNamespaceName( $name ): void {
		$this->assertEquals( $name[1], $this->pp->getNamespaceName( $name[0] ) );
	}

	public function namespaceValues(): array {
		return [
			[ 0, 'NS_MAIN' ],
			[ 6, 'NS_FILE' ],
			[ 10, 'NS_TEMPLATE' ],
			[ 4, 'NS_PROJECT' ],
			[ 8, 'NS_MEDIAWIKI' ]
		];
	}

	/**
	 * @covers       PagePort::getNamespaceByValue
	 * @dataProvider namespaceValues
	 */
	public function testGetNamespaceByValue( $name ) {
		$this->assertEquals( $name[1], $this->pp->getNamespaceByValue( $name[0] ) );
	}

	/**
	 * @covers PagePort::import
	 */
	public function testImport() {
		$tempDir = $this->tempdir('pageprot_');
		$pages = [
			'Page1Test',
			'Page2Test',
			'Page3Test',
			'Page4Test/SubPage1Test',
			'Template:Page5Test',
			'File:Page6Test'
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
	}

	/**
	 * Creates temp directory
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

}
