<?php

$maintPath = ( getenv( 'MW_INSTALL_PATH' ) !== false ? getenv( 'MW_INSTALL_PATH' ) .
													   '/maintenance' : __DIR__ .
																		'/../../../maintenance' );

require_once $maintPath . '/Maintenance.php';

// @codingStandardsIgnoreStart
class PagePortDeletePagesMaintenance extends Maintenance {
// @codingStandardsIgnoreEnd

	/**
	 * ImportPagesMaintenance constructor.
	 *
	 * @param null $args
	 */
	public function __construct( $args = null ) {
		parent::__construct();
		$this->addDescription( 'This script deletes pages that were previous imported to the wiki' );
		$this->addOption( 'source', 'path to load pages from', true, true, 's' );
		$this->addOption( 'user', 'user to make delete edits', false, true, 'u' );
		$this->requireExtension( 'PagePort' );
		if ( $args ) {
			$this->loadWithArgv( $args );
		}
	}

	/**
	 * @return bool|void|null
	 * @throws MWException
	 */
	public function execute() {
		if ( !is_dir( $this->getOption( 'source' ) ) ||
			 !is_writable( $this->getOption( 'source' ) ) ) {
			$this->fatalError( 'Source directory does not exist or you have no read permissions' );
		}

		$root = $this->getOption( 'source' );
		$user = $this->getOption( 'user', null );
		$r = PagePort::getInstance()->delete( $root, $user );
		foreach ( $r as $p ) {
			$this->output( $p['name'] . "\n" );
		}

		$this->output( "Done!\n" );
	}

}

$maintClass = PagePortDeletePagesMaintenance::class;
require_once RUN_MAINTENANCE_IF_MAIN;
