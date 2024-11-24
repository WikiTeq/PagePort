<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Page\WikiPageFactory;
use Wikimedia\Rdbms\ILoadBalancer;

class PagePort {

	/**
	 * @var array
	 */
	private static $constantsCache = [];

	/**
	 * @return PagePort
	 */
	public static function getInstance(): PagePort {
		return MediaWikiServices::getInstance()->getService( 'PagePort' );
	}

	private Language $contentLanguage;
	private ILoadBalancer $loadBalancer;
	private NamespaceInfo $namespaceInfo;
	private WikiPageFactory $wikiPageFactory;

	public function __construct(
		Language $contentLanguage,
		ILoadBalancer $loadBalancer,
		NamespaceInfo $namespaceInfo,
		WikiPageFactory $wikiPageFactory
	) {
		$this->contentLanguage = $contentLanguage;
		$this->loadBalancer = $loadBalancer;
		$this->namespaceInfo = $namespaceInfo;
		$this->wikiPageFactory = $wikiPageFactory;
	}

	/**
	 * @param string $root
	 * @param string|null $user
	 *
	 * @return array
	 */
	public function import( string $root, ?string $user = null ): array {
		if ( $user !== null ) {
			$user = User::newFromName( $user );
		} else {
			$user = RequestContext::getMain()->getUser();
		}
		$pages = $this->getPages( $root );
		foreach ( $pages as $page ) {
			$title = Title::newFromText( $page['fulltitle'] );
			$wp = $this->wikiPageFactory->newFromTitle( $title );
			$wp->doUserEditContent(
				ContentHandler::makeContent( $page['content'], $title ),
				$user,
				'Imported by PagePort'
			);
		}
		return $pages;
	}

	/**
	 * @param string $root
	 * @param string|null $user
	 *
	 * @return array
	 */
	public function delete( string $root, ?string $user = null ): array {
		if ( $user !== null ) {
			$user = User::newFromName( $user );
		} else {
			$user = RequestContext::getMain()->getUser();
		}
		$pages = $this->getPages( $root );
		foreach ( $pages as $page ) {
			$title = Title::newFromText( $page['fulltitle'] );
			$wp = $this->wikiPageFactory->newFromTitle( $title );
			$err = '';
			$wp->doDeleteArticleReal(
				'Deleted by PagePort',
				$user,
				false,
				null,
				$err,
				null,
				[],
				'delete',
				true
			);
		}
		return $pages;
	}

	/**
	 * @param string $root
	 *
	 * @return array
	 */
	private function getPages( string $root ): array {
		if ( !is_dir( $root ) ) {
			throw new InvalidArgumentException(
				'Source directory does not exist or you have no read permissions'
			);
		}
		$list = scandir( $root );
		$pages = [];
		foreach ( $list as $l ) {
			if ( is_dir( $root . '/' . $l ) && $l != '.' && $l != '..' ) {
				$pages = array_merge( $pages, $this->getPagesFromDir( $root . '/' . $l ) );
			}
		}
		return $pages;
	}

	/**
	 * @param string $dir
	 *
	 * @return array
	 */
	private function getPagesFromDir( $dir ): array {
		$pages = [];
		$list = scandir( $dir );
		foreach ( $list as $l ) {
			if ( !is_dir( $dir . '/' . $l ) ) {
				// Skip hidden dirs, like .git
				if ( strpos( basename( $dir ) . '/' . $l, '.' ) === 0 ) {
					continue;
				}

				$namespace = basename( $dir );
				$name = $l;

				$namespace = str_replace( '#', '/', $namespace );
				# Legacy handling for exports that used | rather than #, which
				# was changed for windows support in SEL-1609
				$namespace = str_replace( '|', '/', $namespace );

				$name = str_replace( '.mediawiki', '', $name );
				$name = str_replace( '#', '/', $name );
				# Legacy handling for exports that used | rather than #, which
				# was changed for windows support in SEL-1609
				$name = str_replace( '|', '/', $name );

				$fulltitle = $namespace . ':' . $name;
				// Clean up the Main namespace from the title
				$fulltitle = str_replace( 'Main:', '', $fulltitle );

				$pages[] = [
					// raw name of the file with directory as a namespace
					'name' => $name,
					// file contents
					'content' => file_get_contents( $dir . '/' . $l ),
					// sanitized namespace
					'namespace' => $namespace,
					// sanitized title without a namespace
					'basetitle' => $name,
					// sanitized full title inc. a namespace
					'fulltitle' => $fulltitle
				];
			}
		}
		return $pages;
	}

	/**
	 * @return array
	 */
	public function getAllPages(): array {
		$pages = [];
		$dbr = $this->loadBalancer->getConnection( DB_REPLICA );
		$res = $dbr->select(
			'page',
			[ 'page_title', 'page_namespace' ],
			'',
			__METHOD__
		);
		if ( $res ) {
			foreach ( $res as $row ) {
				$cur_title = Title::makeTitleSafe( $row->page_namespace, $row->page_title );
				if ( $cur_title === null ) {
					continue;
				}
				$cur_value = $this->titleString( $cur_title );
				$pages[] = $cur_value;
			}
		}
		return $pages;
	}

	/**
	 * A very similar function to titleURLString(), to get the
	 * non-URL-encoded title string
	 *
	 * @param Title $title
	 *
	 * @return string
	 */
	private function titleString( $title ): string {
		$namespace = $title->getNsText();
		if ( $namespace !== '' ) {
			$namespace .= ':';
		}
		if ( $this->namespaceInfo->isCapitalized( $title->getNamespace() ) ) {
			return $namespace . $this->contentLanguage->ucfirst( $title->getText() );
		} else {
			return $namespace . $title->getText();
		}
	}

	/**
	 * @param string[] $pages Pages to export
	 * @param string $root output directory path
	 *
	 * @param bool $save save to file
	 *
	 * @return array|bool
	 */
	public function export( array $pages, string $root, bool $save = true ) {
		if ( $save && ( !is_dir( $root ) || !is_writable( $root ) ) ) {
			throw new InvalidArgumentException(
				'Output directory does not exist or you have no write permissions'
			);
		}
		$contents = [];
		foreach ( $pages as $page ) {
			$title = Title::newFromText( $page );
			if ( !$title->exists() ) {
				throw new InvalidArgumentException(
					"The page '" . $page . "' does not exist, export is aborted."
				);
			}
			$filename = $title->getText();
			$namespace = $title->getNamespace();
			$namespaceName = $this->getNamespaceName( $namespace );
			if ( !$namespaceName || $namespaceName == '' ) {
				continue;
			}
			if ( strpos( $namespaceName, '/' ) !== false ) {
				// Used to be replaced with a |, now # for windows support,
				// SEL-1609
				$namespaceName = str_replace( '/', '#', $namespaceName );
			}
			$contentObj = $this->wikiPageFactory->newFromTitle( $title )->getContent();
			$content = $contentObj->getWikitextForTransclusion();
			if ( $save && !file_exists( $root . '/' . $namespaceName ) ) {
				mkdir( $root . '/' . $namespaceName );
			}
			if ( strpos( $filename, '/' ) !== false ) {
				// Used to be replaced with a |, now # for windows support,
				// SEL-1609
				$filename = str_replace( '/', '#', $filename );
			}
			$targetFileName = $root . '/' . $namespaceName . '/' . $filename;
			if ( $contentObj->getModel() === CONTENT_MODEL_WIKITEXT ) {
				$targetFileName .= '.mediawiki';
			}
			if ( $save ) {
				file_put_contents( $targetFileName, $content );
			} else {
				$contents[$targetFileName] = $content;
			}
		}
		if ( !$save ) {
			return $contents;
		}
		return true;
	}

	/**
	 * Returns namespace canonical name
	 *
	 * @param int $namespace
	 *
	 * @return string
	 */
	public function getNamespaceName( int $namespace ): string {
		if ( $namespace === NS_MAIN ) {
			return 'Main';
		}
		return $this->namespaceInfo->getCanonicalName( $namespace );
	}

	/**
	 * Returns namespace constant name (NS_MAIN, NS_FILE, etc) by constant value
	 *
	 * @param string|int $value
	 *
	 * @return array|mixed|null
	 */
	public function getNamespaceByValue( $value ) {
		if ( isset( self::$constantsCache[$value] ) ) {
			return self::$constantsCache[$value];
		}
		$defines = get_defined_constants( true );
		$constants = array_filter(
			$defines['user'],
			static function ( $k ) {
				return strpos( $k, 'NS_' ) !== false;
			},
			ARRAY_FILTER_USE_KEY
		);
		$constants = array_flip( $constants );
		self::$constantsCache = $constants;
		return $constants[$value] ?? null;
	}

	/**
	 * @param string[] $pages Pages to export
	 * @param string $root Output directory
	 * @param string|null $packageName Package name
	 * @param string $packageDesc Package desc
	 *
	 * @param string|null $repo GitHub repository name to substitute wiki URLs
	 *
	 * @param string|null $version Version
	 * @param string|null $author Author
	 * @param string|null $publisher Publisher
	 * @param string[]|null $dependencies Array of dependencies (packages)
	 * @param string[]|null $extensions Array of dependencies (extensions)
	 *
	 * @param bool $save to save resulting JSON
	 *
	 * @return array|bool
	 */
	public function exportJSON(
		array $pages,
		string $root,
		?string $packageName = null,
		string $packageDesc = '',
		?string $repo = null,
		?string $version = null,
		?string $author = null,
		?string $publisher = null,
		?array $dependencies = null,
		?array $extensions = null,
		bool $save = true
	) {
		global $wgLanguageCode;
		if ( $packageName === null ) {
			$packageName = time();
		}
		// Default to 'page-exchange.json'
		$filename = $root . '/page-exchange.json';
		// If root (`out` param) contains a .json file name, use it instead
		if ( strpos( $root, '.json' ) !== false ) {
			$filename = $root;
		}
		$json = [
			'publisher' => $publisher ?: 'PagePort',
			'author' => $author ?: 'PagePort',
			'language' => $wgLanguageCode,
			"url" => "https://github.com/$repo",
			"packages" => [
				$packageName => [
					"globalID" => str_replace( ' ', '.', $packageName ),
					"description" => $packageDesc,
					"version" => $version ?: '0.2',
					"pages" => [],
					"requiredExtensions" => []
				]
			]
		];
		$jsonPages = [];
		foreach ( $pages as $page ) {
			$title = Title::newFromText( $page );
			$name = $title->getText();
			$escapedName = str_replace( '/', '#', $name );
			$namespace = $this->getNamespaceByValue( $title->getNamespace() );
			// PagePort can't handle deprecated NS_IMAGE
			if ( $namespace === "NS_IMAGE" ) {
				$namespace = "NS_FILE";
			}
			$item = [
				"name" => $name,
				"namespace" => $namespace,
				"url" => $title->getFullURL( 'action=raw' )
			];
			if ( $repo !== null ) {
				$item['url'] =
					"https://raw.githubusercontent.com/{$repo}/master/" .
					rawurlencode(
						"{$this->getNamespaceName( $title->getNamespace() )}"
						. "/" . "{$escapedName}.mediawiki"
					);
			}
			$jsonPages[] = $item;
		}
		$json['packages'][$packageName]['pages'] = $jsonPages;
		if ( is_array( $dependencies ) ) {
			foreach ( $dependencies as $dependency ) {
				$json['packages'][$packageName]['requiredPackages'][] = $dependency;
			}
		}
		if ( is_array( $extensions ) ) {
			foreach ( $extensions as $extension ) {
				$json['packages'][$packageName]['requiredExtensions'][] = $extension;
			}
		}
		if ( !$save ) {
			return [ $filename, json_encode( $json, JSON_PRETTY_PRINT ) ];
		}
		file_put_contents( $filename, json_encode( $json, JSON_PRETTY_PRINT ) );
		return true;
	}

	// most of the code below is imported from PageForms

	/**
	 * Get all the pages that belong to a category and all its
	 * subcategories, down a certain number of levels - heavily based on
	 * SMW's SMWInlineQuery::includeSubcategories().
	 *
	 * @param string $top_category
	 * @param int $num_levels
	 * @param string|null $substring
	 * @param bool $inclusive
	 *
	 * @return string[]|string
	 */
	public function getAllPagesForCategory( $top_category, $num_levels, $substring = null, $inclusive = false ) {
		if ( $num_levels == 0 ) {
			return $top_category;
		}
		global $wgPageFormsMaxAutocompleteValues;

		$db = $this->loadBalancer->getConnection( DB_REPLICA );
		$top_category = str_replace( ' ', '_', $top_category );
		$categories = [ $top_category ];
		$checkcategories = [ $top_category ];
		$pages = [];
		$sortkeys = [];
		for ( $level = $num_levels; $level > 0; $level-- ) {
			$newcategories = [];
			foreach ( $checkcategories as $category ) {
				$tables = [ 'categorylinks', 'page' ];
				$columns = [ 'page_title', 'page_namespace' ];
				$conditions = [];
				$conditions[] = 'cl_from = page_id';
				$conditions['cl_to'] = $category;

				$join = [];
				if ( $substring != null ) {
					$conditions[] = $this->getSQLConditionForAutocompleteInColumn(
							'page_title',
							$substring
						) . ' OR page_namespace = ' . NS_CATEGORY;
				}

				$res = $db->select(
					$tables,
					$columns,
					$conditions,
					__METHOD__,
					$options = [
						'ORDER BY' => 'cl_type, cl_sortkey',
						'LIMIT' => $wgPageFormsMaxAutocompleteValues
					],
					$join
				);
				if ( $res ) {
					// @codingStandardsIgnoreStart
					while ( $res && $row = $res->fetchRow() ) {
						// @codingStandardsIgnoreEnd
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
								if ( array_key_exists( 'pp_displaytitle_value', $row )
									 && ( $row['pp_displaytitle_value'] ) !== null
									 && trim( str_replace( '&#160;', '',
										strip_tags( $row['pp_displaytitle_value'] ) ) ) !== ''
								) {
									$pages[$cur_value . '@'] = htmlspecialchars_decode( $row['pp_displaytitle_value'] );
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
					$res->free();
				}
			}
			if ( count( $newcategories ) == 0 ) {
				return $this->fixedMultiSort( $sortkeys, $pages );
			} else {
				$categories = array_merge( $categories, $newcategories );
				if ( $inclusive ) {
					foreach ( $newcategories as $newcategory ) {
						$pages[ 'Category:' . $newcategory . '@' ] = 'Category:' . $newcategory;
						$sortkeys[ 'Category:' . $newcategory ] = 'Category:' . $newcategory;
					}
				}
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
	private function getSQLConditionForAutocompleteInColumn( $column, $substring, $replaceSpaces = true ): string {
		global $wgDBtype, $wgPageFormsAutocompleteOnAllChars;

		$db = $this->loadBalancer->getConnection( DB_REPLICA );

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
			return $column_value . $db->buildLike( $substring, $db->anyString() ) . ' OR ' . $column_value .
				   $db->buildLike(
					   $db->anyString(),
					   $spaceRepresentation . $substring,
					   $db->anyString()
				   );
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
	private function fixedMultiSort( $sortkeys, $pages ): array {
		array_multisort( $sortkeys, $pages );
		$newPages = [];
		foreach ( $pages as $key => $value ) {
			$fixedKey = rtrim( $key, '@' );
			$newPages[$fixedKey] = $value;
		}
		return $newPages;
	}

}
