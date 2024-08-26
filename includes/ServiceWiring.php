<?php

use MediaWiki\MediaWikiServices;

/**
 * Service wiring for PagePort
 * @codeCoverageIgnore
 */
return [
	'PagePort' => static function ( MediaWikiServices $services ): PagePort {
		return new PagePort(
			$services->getContentLanguage(),
			$services->getDBLoadBalancer(),
			$services->getNamespaceInfo(),
			$services->getWikiPageFactory()
		);
	},
];
