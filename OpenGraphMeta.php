<?php
/**
 * OpenGraphMeta
 *
 * @file
 * @ingroup Extensions
 * @author Daniel Friesen (http://danf.ca/mw/)
 * @license GPL-2.0-or-later
 * @link https://www.mediawiki.org/wiki/Extension:OpenGraphMeta Documentation
 */

if ( function_exists( 'wfLoadExtension' ) ) {
	wfLoadExtension( 'OpenGraphMeta' );
	// Keep i18n globals so mergeMessageFileList.php doesn't break
	$wgMessagesDirs['OpenGraphMeta'] = __DIR__ . '/i18n';
	wfWarn(
		'Deprecated PHP entry point used for OpenGraphMeta extension. ' .
		'Please use wfLoadExtension instead, ' .
		'see https://www.mediawiki.org/wiki/Extension_registration for more details.'
	);
	return;
} else {
	die( 'This version of the OpenGraphMeta extension requires MediaWiki 1.25+' );
}
