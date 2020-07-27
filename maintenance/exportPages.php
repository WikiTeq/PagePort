<?php

$maintPath = ( getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) .
													   '/maintenance' : __DIR__ .
																		'/../../../maintenance' );

require_once $maintPath . '/Maintenance.php';

class PagePortExportMaintenance extends Maintenance {

	public function __construct( $args = null ) {
		parent::__construct();

		$this->addDescription( 'This script exports selected pages into a git-ready structure' );
		$this->addOption( 'out', 'directory to save exported pages or json filename if exporting JSON', true, true, 'o' );
		$this->addOption( 'category', 'category to be exported', false, true, 'c' );
		$this->addOption( 'pagelist', 'file with pages to be exported', false, true, 'l' );
		$this->addOption( 'zip', 'archive name to compress resulting files', false, true, 'z' );
		$this->addOption( 'full', 'export the whole wiki', false, false, 'f' );

		$this->addOption( 'json', 'export as JSON compatible with PageExchange', false, false, 'j' );
		$this->addOption( 'package', 'package name (JSON only)', false, false, 'p' );
		$this->addOption( 'desc', 'package description (JSON only)', false, false, 'd' );
		$this->addOption( 'github', 'github repository name to use in JSON export urls', false, true, 'g' );
		$this->addOption( 'version', 'JSON package version', false, true, 'v' );
		$this->addOption( 'author', 'JSON package author', false, true, 'a' );
		$this->addOption( 'publisher', 'JSON package publisher', false, true, 'u' );

		$this->requireExtension( 'PagePort' );
		if ( $args ) {
			$this->loadWithArgv( $args );
		}
	}

	public function execute() {

		$json = $this->getOption( 'json' );

		if ( (!$json && !is_dir( $this->getOption( 'out' ) ) ) || !is_writable( dirname( $this->getOption( 'out' ) ) ) ) {
			$this->fatalError( 'Output directory does not exist or you have no write permissions' );
		}

		if ( !$this->getOption('category') && !$this->getOption('pagelist') && !$this->getOption('full') ) {
			$this->fatalError( 'Either --full, --category or --pagelist parameter need to be specified.' );
		}

		if ( $this->getOption('category') && $this->getOption('pagelist') && $this->getOption('full') ) {
			$this->fatalError( 'Either --full, --category or --pagelist parameter need to be specified.' );
		}

		$pages = [];
		$root = $this->getOption( 'out' );
		$category = $this->getOption( 'category' );
		$pagelist = $this->getOption( 'pagelist' );
		$zipfile = $this->getOption('zip');
		$full = $this->getOption('full');
		$package = $this->getOption('package', null);
		$desc = $this->getOption('desc');
		$github = $this->getOption('github');
		$version = $this->getOption('version');
		$author = $this->getOption('author');
		$publisher = $this->getOption('publisher');

		if( $category ) {
			if ( !in_array( str_replace( ' ', '_', $this->getOption( 'category' ) ),
				PagePort::getInstance()->getAllCategories() )
			) {
				$this->fatalError( 'The category specified does not exits, check for typos.' );
			}
			// TODO: test with displaytitle overrides!!
			$pages = PagePort::getInstance()->getAllPagesForCategory( $category, 1 );
		}
		if ( $pagelist ) {
			if ( !file_exists( $pagelist) ) {
				$this->fatalError( 'The pagelist file does not exist or you have no read permission.' );
			}
			$pages = file($pagelist, FILE_IGNORE_NEW_LINES);
		}
		if( $full ) {
			$pages = PagePort::getInstance()->getAllPages();
		}
		if ( !count( $pages ) ) {
			$this->fatalError( 'There is nothing to export!' );
		}
		try {
			if( $json ) {
				PagePort::getInstance()->exportJSON(
					$pages, $root, $package, $desc, $github, $version, $author, $publisher
				);
			}else {
				PagePort::getInstance()->export( $pages, $root );
			}
			if ( $zipfile ) {
				$zip = new ZipArchive();
				$filename = $root . '/' . $zipfile;
				if ( $zip->open( $filename, ZipArchive::CREATE | ZipArchive::OVERWRITE ) !== TRUE ) {
					$this->fatalError( 'Unable to create a zip archive!' );
				}
				$files = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator($root),
					RecursiveIteratorIterator::LEAVES_ONLY
				);
				foreach ($files as $name => $file) {
					// Skip directories (they would be added automatically)
					if (!$file->isDir()) {
						// Get real and relative path for current file
						$filePath = $file->getRealPath();
						$relativePath = substr($filePath, strlen(realpath($root)) + 1);
						// Add current file to archive
						$zip->addFile($filePath, $relativePath);
					}
				}
				$zip->close();
				$this->output("Zip archive is created.\n");
			}
		} catch ( Exception $e ) {
			$this->fatalError( $e->getMessage() );
		}

		$this->output( "Done!" );

	}

}

$maintClass = PagePortExportMaintenance::class;
require_once RUN_MAINTENANCE_IF_MAIN;
