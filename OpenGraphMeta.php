<?php
/**
 * OpenGraphMeta
 *
 * @file
 * @ingroup Extensions
 * @author Daniel Friesen (http://danf.ca/mw/)
 * @license https://www.gnu.org/copyleft/gpl.html GNU General Public License 2.0 or later
 * @link https://www.mediawiki.org/wiki/Extension:OpenGraphMeta Documentation
 */

if ( !defined( 'MEDIAWIKI' ) ) die( "This is an extension to the MediaWiki package and cannot be run standalone." );

$wgExtensionCredits['parserhook'][] = array(
	'path' => __FILE__,
	'name' => "OpenGraphMeta",
	'author' => "[http://danf.ca/mw/ Daniel Friesen]",
	'descriptionmsg' => 'opengraphmeta-desc',
	'url' => 'https://www.mediawiki.org/wiki/Extension:OpenGraphMeta',
	'license-name' => 'GPL-2.0+',
);

$dir = dirname( __FILE__ );
$wgExtensionMessagesFiles['OpenGraphMetaMagic'] = $dir . '/OpenGraphMeta.magic.php';
$wgMessagesDirs['OpenGraphMeta'] = __DIR__ . '/i18n';
$wgExtensionMessagesFiles['OpenGraphMeta'] = $dir . '/OpenGraphMeta.i18n.php';

$wgHooks['ParserFirstCallInit'][] = 'efOpenGraphMetaParserInit';
function efOpenGraphMetaParserInit( $parser ) {
	$parser->setFunctionHook( 'setmainimage', 'efSetMainImagePF' );
	return true;
}

function efSetMainImagePF( $parser, $mainimage ) {
	$parserOutput = $parser->getOutput();
	if ( isset($parserOutput->eHasMainImageAlready) && $parserOutput->eHasMainImageAlready )
		return $mainimage;
	$file = Title::newFromText( $mainimage, NS_FILE );
	$parserOutput->addOutputHook( 'setmainimage', array( 'dbkey' => $file->getDBkey() ) );
	$parserOutput->eHasMainImageAlready = true;

	return $mainimage;
}

$wgParserOutputHooks['setmainimage'] = 'efSetMainImagePH';
function efSetMainImagePH( $out, $parserOutput, $data ) {
	$out->mMainImage = wfFindFile($data['dbkey']);
}

$wgHooks['BeforePageDisplay'][] = 'efOpenGraphMetaPageHook';
function efOpenGraphMetaPageHook( &$out, &$sk ) {
	global $wgLogo, $wgSitename, $wgXhtmlNamespaces, $egFacebookAppId, $egFacebookAdmins;
	$wgXhtmlNamespaces["og"] = "http://opengraphprotocol.org/schema/";
	$title = $out->getTitle();
	$isMainpage = $title->isMainPage();

	$meta = array();

	if ( $isMainpage ) {
		$meta["og:type"] = "website";
		$meta["og:title"] = $wgSitename;
	} else {
		$meta["og:type"] = "article";
		$meta["og:site_name"] = $wgSitename;
		// Try to chose the most appropriate title for showing in news feeds.
		if ( ( defined('NS_BLOG_ARTICLE') && $title->getNamespace() == NS_BLOG_ARTICLE ) ||
			( defined('NS_BLOG_ARTICLE_TALK') && $title->getNamespace() == NS_BLOG_ARTICLE_TALK ) ){
			$meta["og:title"] = $title->getSubpageText();
		} else {
			$meta["og:title"] = $title->getText();
		}
	}

	if ( isset( $out->mMainImage ) && ( $out->mMainImage !== false ) ) {
		if( is_object( $out->mMainImage ) ){
			$meta["og:image"] = wfExpandUrl($out->mMainImage->createThumb(100*3, 100));
		} else {
			// In some edge-cases we won't have defined an object but rather a full URL.
			$meta["og:image"] = $out->mMainImage;
		}
	} elseif ( $isMainpage ) {
		$meta["og:image"] = wfExpandUrl($wgLogo);
	}
	if ( isset($out->mDescription) ) { // set by Description2 extension, install it if you want proper og:description support
		$meta["og:description"] = $out->mDescription;
	}
	$meta["og:url"] = $title->getFullURL();
	if ( $egFacebookAppId ) {
		$meta["fb:app_id"] = $egFacebookAppId;
	}
	if ( $egFacebookAdmins ) {
		$meta["fb:admins"] = $egFacebookAdmins;
	}

	foreach( $meta as $property => $value ) {
		if ( $value ) {
			if ( isset( OutputPage::$metaAttrPrefixes ) && isset( OutputPage::$metaAttrPrefixes['property'] ) ) {
				$out->addMeta( "property:$property", $value );
			} else {
				$out->addHeadItem("meta:property:$property", "	".Html::element( 'meta', array( 'property' => $property, 'content' => $value ) )."\n");
			}
		}
	}

	return true;
}

$egFacebookAppId = null;
$egFacebookAdmins = null;

