<?php

/**
 * Hooks for PagePort extension
 *
 * @file
 * @ingroup Extensions
 */
class PagePortHooks {

	/**
	 * @param string[] &$paths
	 *
	 * @return bool
	 */
	public static function onUnitTestsList( &$paths ) {
		$paths[] = __DIR__ . '/tests/phpunit/';
		return true;
	}

}
