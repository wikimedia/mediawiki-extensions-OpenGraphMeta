<?php
/**
 * OpenGraphMeta
 *
 * @file
 * @ingroup Extensions
 * @author Daniel Friesen (http://danf.ca/mw/)
 * @author Southparkfan
 * @license GPL-2.0-or-later
 * @link https://www.mediawiki.org/wiki/Extension:OpenGraphMeta Documentation
 */

use MediaWiki\MediaWikiServices;

class OpenGraphMeta {
	/**
	 * @param Parser $parser
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setFunctionHook( 'setmainimage', [ __CLASS__, 'setMainImagePF' ] );
		$parser->setFunctionHook( 'setmaintitle', [ __CLASS__, 'setMainTitlePF' ] );
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
		if ( $file !== null ) {
			$parserOutput->setExtensionData( 'setmainimage', $file->getDBkey() );
		}

		return $mainImage;
	}

	/**
	 * @param Parser $parser
	 * @param string $mainTitle
	 * @return string
	 */
	public static function setMainTitlePF( Parser $parser, $mainTitle ) {
		$parserOutput = $parser->getOutput();
		$setMainTitle = $parserOutput->getExtensionData( 'setmaintitle' );
		if ( $setMainTitle !== null ) {
			return $mainTitle;
		}

		$parserOutput->setExtensionData( 'setmaintitle', $mainTitle );

		return $mainTitle;
	}

	/**
	 * @param OutputPage &$out
	 * @param ParserOutput $parserOutput
	 */
	public static function onOutputPageParserOutput( OutputPage &$out, ParserOutput $parserOutput ) {
		global $wgLogo, $wgSitename, $wgXhtmlNamespaces, $egFacebookAppId, $egFacebookAdmins;

		$setMainImage = $parserOutput->getExtensionData( 'setmainimage' );
		$setMainTitle = $parserOutput->getExtensionData( 'setmaintitle' );

		if ( $setMainImage !== null ) {
			$mainImage = MediaWikiServices::getInstance()->getRepoGroup()
				->findFile( Title::newFromText( $setMainImage, NS_FILE ) );
		} else {
			$mainImage = false;
		}

		$wgXhtmlNamespaces['og'] = 'http://opengraphprotocol.org/schema/';
		$title = $out->getTitle();
		$isMainpage = $title->isMainPage();

		$meta = [];

		if ( $isMainpage ) {
			$meta['og:type'] = 'website';
			$meta['og:title'] = $wgSitename;
		} else {
			$meta['og:type'] = 'article';
			$meta['og:site_name'] = $wgSitename;
			// Try to choose the most appropriate title for showing in news feeds.
			if (
				( defined( 'NS_BLOG_ARTICLE' ) && $title->getNamespace() == NS_BLOG_ARTICLE ) ||
				( defined( 'NS_BLOG_ARTICLE_TALK' ) && $title->getNamespace() == NS_BLOG_ARTICLE_TALK )
			) {
				$meta['og:title'] = $title->getSubpageText();
			} else {
				$meta['og:title'] = $title->getText();
			}
		}

		// {{#setmaintitle}} was used, override og:title value
		if ( $setMainTitle !== null ) {
			$meta['og:title'] = $setMainTitle;
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
			// fb:app_id needs a prefix property declaring the namespace, so just add it directly
			$out->addHeadItem(
				'meta:property:fb:app_id',
				'	' . Html::element( 'meta', [
					'property' => 'fb:app_id',
					'content' => $egFacebookAppId,
					'prefix' => 'fb: http://www.facebook.com/2008/fbml'
				] ) . "\n"
			);
		}
		if ( $egFacebookAdmins ) {
			$meta['fb:admins'] = $egFacebookAdmins;
		}

		// Add og:image to <meta> tags instead of as a <head> item to get it earlier in the page
		if ( isset( $meta['og:image'] ) ) {
			$out->addMeta( 'og:image', $meta['og:image'] );
			unset( $meta['og:image'] );
		}

		Hooks::run( 'OpenGraphMetaHeaders', [ &$meta, $title, $out, $parserOutput ] );

		foreach ( $meta as $property => $value ) {
			if ( $value ) {
				$out->addHeadItem(
					"meta:property:$property",
					'	' . Html::element( 'meta', [
						'property' => $property,
						'content' => $value
					] ) . "\n"
				);
			}
		}
	}

}
