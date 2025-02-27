<?php

namespace MailPoet\Form\Util;

use MailPoet\WP\Functions;

class CustomFonts {
  const FONT_CHUNK_SIZE = 25;
  const FONTS = [
    'Abril FatFace',
    'Alegreya',
    'Alegreya Sans',
    'Amatic SC',
    'Anonymous Pro',
    'Architects Daughter',
    'Archivo',
    'Archivo Narrow',
    'Asap',
    'Barlow',
    'BioRhyme',
    'Bonbon',
    'Cabin',
    'Cairo',
    'Cardo',
    'Chivo',
    'Concert One',
    'Cormorant',
    'Crimson Text',
    'Eczar',
    'Exo 2',
    'Fira Sans',
    'Fjalla One',
    'Frank Ruhl Libre',
    'Great Vibes',
    'Heebo',
    'IBM Plex',
    'Inconsolata',
    'Indie Flower',
    'Inknut Antiqua',
    'Inter',
    'Karla',
    'Libre Baskerville',
    'Libre Franklin',
    'Montserrat',
    'Neuton',
    'Notable',
    'Nothing You Could Do',
    'Noto Sans',
    'Nunito',
    'Old Standard TT',
    'Oxygen',
    'Pacifico',
    'Poppins',
    'Proza Libre',
    'PT Sans',
    'PT Serif',
    'Rakkas',
    'Reenie Beanie',
    'Roboto Slab',
    'Ropa Sans',
    'Rubik',
    'Shadows Into Light',
    'Space Mono',
    'Spectral',
    'Sue Ellen Francisco',
    'Titillium Web',
    'Ubuntu',
    'Varela',
    'Vollkorn',
    'Work Sans',
    'Yatra One',
  ];

  /** @var Functions */
  private $wp;

  public function __construct(
    Functions $wp
  ) {
    $this->wp = $wp;
  }

  public function enqueueStyle() {
    $displayCustomFonts = $this->wp->applyFilters('mailpoet_display_custom_fonts', true);
    if ($displayCustomFonts) {
      // Due to a conflict with the WooCommerce Payments plugin, we need to load custom fonts in more requests.
      // When we load all custom fonts in one request, a form from WC Payments isn't displayed correctly.
      // It looks that the larger file size overloads the Stripe SDK.
      foreach (array_chunk(self::FONTS, self::FONT_CHUNK_SIZE) as $key => $fonts) {
        $this->wp->wpEnqueueStyle('mailpoet_custom_fonts_' . $key, $this->generateLink($fonts));
      }
    }
  }

  public function generateHtmlCustomFontLink() {
    $output = '';

    foreach (array_chunk(self::FONTS, self::FONT_CHUNK_SIZE) as $key => $fonts) {
      $output .= sprintf('<link href="%s" rel="stylesheet">', $this->generateLink($fonts));
    }

    return $output;
  }

  private function generateLink(array $fonts): string {
    $fonts = array_map(function ($fontName) {
      return urlencode($fontName) . ':400,400i,700,700i';
    }, $fonts);
    $fonts = implode('|', $fonts);
    return 'https://fonts.googleapis.com/css?family=' . $fonts;
  }
}
