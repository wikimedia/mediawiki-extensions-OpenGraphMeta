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

class OpenGraphMeta {

	/**
	 * @param Parser $parser
	 * @return bool
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setFunctionHook( 'setmainimage', array( __CLASS__, 'setMainImagePF' ) );
		return true;
	}

	/**
	 * @param Parser $parser
	 * @param string $mainImage
	 * @return string
	 */
	public static function setMainImagePF( Parser $parser, $mainImage ) {
		$parserOutput = $parser->getOutput();
		$setMainImage = $parserOutput->getExtensionData( 'setmainimage' );
		if ( $setMainImage !== null ) {
			return $mainImage;
		}

		$file = Title::newFromText( $mainImage, NS_FILE );
		$parserOutput->setExtensionData( 'setmainimage', $file->getDBkey() );

		return $mainImage;
	}

	public static function onOutputPageParserOutput( OutputPage &$out, ParserOutput $parserOutput ) {
		global $wgLogo, $wgSitename, $wgXhtmlNamespaces, $egFacebookAppId, $egFacebookAdmins;

		$setMainImage = $parserOutput->getExtensionData( 'setmainimage' );
		if ( $setMainImage !== null ) {
			$mainImage = wfFindFile( Title::newFromDBkey( $setMainImage ) );
		} else {
			$mainImage = false;
		}

		$wgXhtmlNamespaces['og'] = 'http://opengraphprotocol.org/schema/';
		$title = $out->getTitle();
		$isMainpage = $title->isMainPage();

		$meta = array();

		if ( $isMainpage ) {
			$meta['og:type'] = 'website';
			$meta['og:title'] = $wgSitename;
		} else {
			$meta['og:type'] = 'article';
			$meta['og:site_name'] = $wgSitename;
			// Try to chose the most appropriate title for showing in news feeds.
			if (
				( defined( 'NS_BLOG_ARTICLE' ) && $title->getNamespace() == NS_BLOG_ARTICLE ) ||
				( defined( 'NS_BLOG_ARTICLE_TALK' ) && $title->getNamespace() == NS_BLOG_ARTICLE_TALK )
			) {
				$meta['og:title'] = $title->getSubpageText();
			} else {
				$meta['og:title'] = $title->getText();
			}
		}

		if ( ( $mainImage !== false ) ) {
			if ( is_object( $mainImage ) ) {
				// The official OpenGraph documentation says:
				// - thumbnail previews can't be smaller than 200px x 200px
				// - thumbnail previews look best at 1200px x 630px
				// @see https://developers.facebook.com/docs/sharing/best-practices/
				// @see https://phabricator.wikimedia.org/T193986
				$meta['og:image'] = wfExpandUrl( $mainImage->createThumb( 1200, 630 ) );
			} else {
				// In some edge-cases we won't have defined an object but rather a full URL.
				$meta['og:image'] = $mainImage;
			}
		} elseif ( $isMainpage ) {
			$meta['og:image'] = wfExpandUrl( $wgLogo );
		}
		$description = $parserOutput->getProperty( 'description' );
		if ( $description !== false ) { // set by Description2 extension, install it if you want proper og:description support
			$meta['og:description'] = $description;
		}
		$meta['og:url'] = $title->getFullURL();
		if ( $egFacebookAppId ) {
			$meta['fb:app_id'] = $egFacebookAppId;
		}
		if ( $egFacebookAdmins ) {
			$meta['fb:admins'] = $egFacebookAdmins;
		}

		foreach( $meta as $property => $value ) {
			if ( $value ) {
				$out->addHeadItem(
					"meta:property:$property",
					'	' . Html::element( 'meta', array(
						'property' => $property,
						'content' => $value
					) ) . "\n"
				);
			}
		}

		return true;
	}

}
