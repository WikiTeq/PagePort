<?php

use MediaWiki\MediaWikiServices;

class PagePort {

	public static $instance;

	public static function getInstance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	public function import( $root, $user = null ) {
		if ( !is_dir( $root ) ) {
			throw new Exception( 'Source directory does not exist or you have no read permissions' );
		}
		if ( $user !== null ) {
			$user = User::newFromName( $user );
		}
		$list = scandir( $root );
		$pages = [];
		foreach ( $list as $l ) {
			if ( is_dir( $root . '/' . $l ) && $l != '.' && $l != '..' ) {
				$pages = array_merge( $pages, $this->getPagesFromDir( $root . '/' . $l ) );
			}
		}
		foreach ( $pages as $page ) {
			$parts = explode( ':', $page['name'] );
			// TODO: better sanitizing!
			$namespace = $parts[0] == 'Main' ? false : $parts[0];
			if ( strpos( $namespace, '|' ) !== false ) {
				$namespace = str_replace( '|', '/', $namespace );
			}
			$name = str_replace('.mediawiki', '', $parts[1] );
			if ( strpos( $name, '|' ) !== false ) {
				$name = str_replace( '|', '/', $name );
			}
			$fulltext = ( $namespace ? $namespace . ':' : '' ) . $name;
			$title = Title::newFromText( $fulltext );
			$wp = WikiPage::factory( $title );
			$wp->doEditContent( new WikitextContent( $page['content'] ), 'Imported by PagePort', 0, false, $user );
		}
		return $pages;
	}

	private function getPagesFromDir( $dir ) {
		$pages = [];
		$list = scandir( $dir );
		foreach ( $list as $l ) {
			if ( !is_dir( $dir . '/' . $l ) ) {
				$pages[] = [
					'name' => basename( $dir ) . ':' . $l,
					'content' => file_get_contents( $dir . '/' . $l )
				];
			}
		}
		return $pages;
	}

	public function getAllPages() {
		$pages = [];
		$dbr = wfGetDB( DB_REPLICA );
		$res = $dbr->select(
			'page',
			['page_title', 'page_namespace']
		);
		if ( $res ) {
			while ( $res && $row = $dbr->fetchRow( $res ) ) {
				$cur_title = Title::makeTitleSafe( $row['page_namespace'], $row['page_title'] );
				if ( $cur_title === null ) { continue; }
				$cur_value = $this->titleString( $cur_title );
				$pages[] = $cur_value;
			}
		}
		return $pages;
	}

	/**
	 * @param string[] $pages Pages to export
	 * @param string $root output directory path
	 *
	 * @return bool
	 * @throws MWException
	 * @throws Exception
	 */
	public function export( $pages, $root ) {
		if ( !is_dir( $root ) || !is_writable( $root ) ) {
			throw new Exception( 'Output directory does not exist or you have no write permissions' );
		}
		foreach ( $pages as $page ) {
			$title = Title::newFromText( $page );
			$filename = $title->getText();
			$namespace = $title->getNamespace();
			$namespaceName = 'Main';
			if ( $namespace !== NS_MAIN ) {
				if( class_exists('LanguageConverterFactory') ) {
					$namespaceName = MediaWikiServices::getInstance()->getContentLanguage()
						->convertNamespace( $namespace );
				}else{
					global $wgContLang;
					$namespaceName = $wgContLang->convertNamespace( $namespace );
				}
				if ( !$namespaceName || $namespaceName == '' ) {
					continue;
				}
			}
			// TODO: better sanitizing!
			if ( strpos( $namespaceName, '/' ) !== false ) {
				$namespaceName = str_replace( '/', '|', $namespaceName );
			}
			$content = WikiPage::factory( $title )->getContent()->getWikitextForTransclusion();
			if ( !file_exists( $root . '/' . $namespaceName ) ) {
				mkdir( $root . '/' . $namespaceName );
			}
			if ( strpos( $filename, '/' ) !== false ) {
				$filename = str_replace( '/', '|', $filename );
			}
			file_put_contents( $root . '/' . $namespaceName . '/' . $filename .
							   '.mediawiki', $content );
		}
		return true;
	}

	// most of the code below is imported from PageForms

	/**
	 * Helper function - returns names of all the categories.
	 * @return array
	 */
	public function getAllCategories() {
		$categories = [];
		$db = wfGetDB( DB_REPLICA );
		$res = $db->select( 'category', 'cat_title', null, __METHOD__ );
		if ( $db->numRows( $res ) > 0 ) {
			while ( $row = $db->fetchRow( $res ) ) {
				$categories[] = $row['cat_title'];
			}
		}
		$db->freeResult( $res );
		return $categories;
	}

	/**
	 * Get all the pages that belong to a category and all its
	 * subcategories, down a certain number of levels - heavily based on
	 * SMW's SMWInlineQuery::includeSubcategories().
	 *
	 * @param string $top_category
	 * @param int $num_levels
	 * @param string|null $substring
	 *
	 * @return string
	 */
	public function getAllPagesForCategory( $top_category, $num_levels, $substring = null ) {
		if ( 0 == $num_levels ) {
			return $top_category;
		}
		global $wgPageFormsMaxAutocompleteValues, $wgPageFormsUseDisplayTitle;

		$db = wfGetDB( DB_REPLICA );
		$top_category = str_replace( ' ', '_', $top_category );
		$categories = [ $top_category ];
		$checkcategories = [ $top_category ];
		$pages = [];
		$sortkeys = [];
		for ( $level = $num_levels; $level > 0; $level -- ) {
			$newcategories = [];
			foreach ( $checkcategories as $category ) {
				$tables = [ 'categorylinks', 'page' ];
				$columns = [ 'page_title', 'page_namespace' ];
				$conditions = [];
				$conditions[] = 'cl_from = page_id';
				$conditions['cl_to'] = $category;
				if ( $wgPageFormsUseDisplayTitle ) {
					$tables['pp_displaytitle'] = 'page_props';
					$tables['pp_defaultsort'] = 'page_props';
					$columns['pp_displaytitle_value'] = 'pp_displaytitle.pp_value';
					$columns['pp_defaultsort_value'] = 'pp_defaultsort.pp_value';
					$join = [
						'pp_displaytitle' => [
							'LEFT JOIN',
							[
								'pp_displaytitle.pp_page = page_id',
								'pp_displaytitle.pp_propname = \'displaytitle\''
							]
						],
						'pp_defaultsort' => [
							'LEFT JOIN',
							[
								'pp_defaultsort.pp_page = page_id',
								'pp_defaultsort.pp_propname = \'defaultsort\''
							]
						]
					];
					if ( $substring != null ) {
						$conditions[] = '(pp_displaytitle.pp_value IS NULL AND (' .
										$this->getSQLConditionForAutocompleteInColumn( 'page_title', $substring ) .
										')) OR ' .
										$this->getSQLConditionForAutocompleteInColumn( 'pp_displaytitle.pp_value', $substring ) .
										' OR page_namespace = ' . NS_CATEGORY;
					}
				} else {
					$join = [];
					if ( $substring != null ) {
						$conditions[] = $this->getSQLConditionForAutocompleteInColumn( 'page_title', $substring ) .
										' OR page_namespace = ' . NS_CATEGORY;
					}
				}
				$res = $db->select( // make the query
					$tables, $columns, $conditions, __METHOD__, $options = [
					'ORDER BY' => 'cl_type, cl_sortkey',
					'LIMIT' => $wgPageFormsMaxAutocompleteValues
				], $join );
				if ( $res ) {
					while ( $res && $row = $db->fetchRow( $res ) ) {
						if ( !array_key_exists( 'page_title', $row ) ) {
							continue;
						}
						$page_namespace = $row['page_namespace'];
						$page_name = $row['page_title'];
						if ( $page_namespace == NS_CATEGORY ) {
							if ( !in_array( $page_name, $categories ) ) {
								$newcategories[] = $page_name;
							}
						} else {
							$cur_title = Title::makeTitleSafe( $page_namespace, $page_name );
							if ( $cur_title === null ) {
								// This can happen if it's
								// a "phantom" page, in a
								// namespace that no longer exists.
								continue;
							}
							$cur_value = $this->titleString( $cur_title );
							if ( !in_array( $cur_value, $pages ) ) {
								if ( array_key_exists( 'pp_displaytitle_value', $row ) &&
									 ( $row['pp_displaytitle_value'] ) !== null &&
									 trim( str_replace( '&#160;', '', strip_tags( $row['pp_displaytitle_value'] ) ) ) !==
									 '' ) {
									$pages[$cur_value .
										   '@'] = htmlspecialchars_decode( $row['pp_displaytitle_value'] );
								} else {
									$pages[$cur_value . '@'] = $cur_value;
								}
								if ( array_key_exists( 'pp_defaultsort_value', $row ) &&
									 ( $row['pp_defaultsort_value'] ) !== null ) {
									$sortkeys[$cur_value] = $row['pp_defaultsort_value'];
								} else {
									$sortkeys[$cur_value] = $cur_value;
								}
							}
						}
					}
					$db->freeResult( $res );
				}
			}
			if ( count( $newcategories ) == 0 ) {
				return $this->fixedMultiSort( $sortkeys, $pages );
			} else {
				$categories = array_merge( $categories, $newcategories );
			}
			$checkcategories = array_diff( $newcategories, [] );
		}
		return $this->fixedMultiSort( $sortkeys, $pages );
	}

	/**
	 * Returns a SQL condition for autocompletion substring value in a column.
	 *
	 * @param string $column Value column name
	 * @param string $substring Substring to look for
	 * @param bool $replaceSpaces
	 *
	 * @return string SQL condition for use in WHERE clause
	 */
	private function getSQLConditionForAutocompleteInColumn( $column, $substring, $replaceSpaces = true ) {
		global $wgDBtype, $wgPageFormsAutocompleteOnAllChars;

		$db = wfGetDB( DB_REPLICA );

		// CONVERT() is also supported in PostgreSQL, but it doesn't
		// seem to work the same way.
		if ( $wgDBtype == 'mysql' ) {
			$column_value = "LOWER(CONVERT($column USING utf8))";
		} else {
			$column_value = "LOWER($column)";
		}

		$substring = strtolower( $substring );
		if ( $replaceSpaces ) {
			$substring = str_replace( ' ', '_', $substring );
		}

		if ( $wgPageFormsAutocompleteOnAllChars ) {
			return $column_value . $db->buildLike( $db->anyString(), $substring, $db->anyString() );
		} else {
			$spaceRepresentation = $replaceSpaces ? '_' : ' ';
			return $column_value . $db->buildLike( $substring, $db->anyString() ) . ' OR ' .
				   $column_value . $db->buildLike( $db->anyString(), $spaceRepresentation .
																	 $substring, $db->anyString() );
		}
	}

	/**
	 * array_multisort() unfortunately messes up array keys that are
	 * numeric - they get converted to 0, 1, etc. There are a few ways to
	 * get around this, but I (Yaron) couldn't get those working, so
	 * instead we're going with this hack, where all key values get
	 * appended with a '@' before sorting, which is then removed after
	 * sorting. It's inefficient, but it's probably good enough.
	 *
	 * @param string[] $sortkeys
	 * @param string[] $pages
	 *
	 * @return string[] a sorted version of $pages, sorted via $sortkeys
	 */
	private function fixedMultiSort( $sortkeys, $pages ) {
		array_multisort( $sortkeys, $pages );
		$newPages = [];
		foreach ( $pages as $key => $value ) {
			$fixedKey = rtrim( $key, '@' );
			$newPages[$fixedKey] = $value;
		}
		return $newPages;
	}

	/**
	 * A very similar function to titleURLString(), to get the
	 * non-URL-encoded title string
	 *
	 * @param Title $title
	 *
	 * @return string
	 */
	private function titleString( $title ) {
		$namespace = $title->getNsText();
		if ( $namespace !== '' ) {
			$namespace .= ':';
		}
		if ( MWNamespace::isCapitalized( $title->getNamespace() ) ) {
			return $namespace . $this->getContLang()->ucfirst( $title->getText() );
		} else {
			return $namespace . $title->getText();
		}
	}

	private function getContLang() {
		if ( method_exists( "MediaWiki\\MediaWikiServices", "getContentLanguage" ) ) {
			return MediaWikiServices::getInstance()->getContentLanguage();
		} else {
			global $wgContLang;
			return $wgContLang;
		}
	}

}
