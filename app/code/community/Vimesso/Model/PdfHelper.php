<?php

/**
 * Magento
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 *
 * @copyright  Copyright (c) 2009 Maison du Logiciel (http://www.maisondulogiciel.com)
 * @author : Olivier ZIMMERMANN
 * @license    http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
abstract class Vimesso_Model_PdfHelper extends Mage_Sales_Model_Order_Pdf_Abstract {

     protected $_BLOC_ENTETE_HAUTEUR = 50;
    protected $_BLOC_ENTETE_LARGEUR = 585;
    protected $_BLOC_FOOTER_HAUTEUR = 40;
    protected $_BLOC_FOOTER_LARGEUR = 585;
    protected $_LOGO_HAUTEUR = 40;
    protected $_LOGO_LARGEUR = 200;
    protected $_PAGE_HEIGHT = 842;
    protected $_PAGE_WIDTH = 595;
    protected $_ITEM_HEIGHT = 25;
    public $pdf;
    protected $firstPageIndex = 0;

    /**
     * Insert le logo
     *
     * @param unknown_type $page
     */
    protected function insertLogo(&$page, $StoreId = null) {
        $image = Mage::getStoreConfig('sales/identity/logo', $StoreId);
        if ($image) {
            $image = Mage::getStoreConfig('system/filesystem/media') . '/sales/store/logo/' . $image;
            if (is_file($image)) {
                $image = Zend_Pdf_Image::imageWithPath($image);
                $page->drawImage($image, 25, 785, 25 + $this->_LOGO_LARGEUR, 785 + $this->_LOGO_HAUTEUR);
            }
        }
        //return $page;
    }

    /**
     * Dessine un texte multiligne
     * retourne la taille en hauteur totale
     */
    protected function DrawMultilineText(&$page, $Text, $x, $y, $Size, $GrayScale, $LineHeight) {
        $retour = -$LineHeight;
        $page->setFillColor(new Zend_Pdf_Color_GrayScale($GrayScale));
        $page->setFont(Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_HELVETICA), $Size);
        foreach (explode("\n", $Text) as $value) {
            if ($value !== '') {
                $page->drawText(trim(strip_tags($value)), $x, $y, 'UTF-8');
                $y -=$LineHeight;
                $retour += $LineHeight;
            }
        }
        return $retour;
    }

    /**
     * Retourne la largeur d'un text (par rapport � la police et la taille
     */
    public function widthForStringUsingFontSize($string, $font, $fontSize) {
        try {
            //fix iconv issue
            $workingString = '';
            for ($i = 0; $i < strlen($string); $i++) {
                if (ord($string{$i}) < 128)
                    $workingString .= $string{$i};
            }

            $drawingString = iconv('UTF-8', 'UTF-16BE//IGNORE', $workingString);
            $characters = array();
            for ($i = 0; $i < strlen($drawingString); $i++) {
                $characters[] = (ord($drawingString[$i++]) << 8 ) | ord($drawingString[$i]);
            }
            $glyphs = $font->glyphNumbersForCharacters($characters);
            $widths = $font->widthsForGlyphs($glyphs);
            $stringWidth = (array_sum($widths) / $font->getUnitsPerEm()) * $fontSize;
            return $stringWidth;
        } catch (Exception $ex) {
            die("Erreur dans Mdn pdf helper m�thode widthForStringUsingFontSize avec string = " . $string . ' - ' . $ex->getMessage() . ' - ' . $ex->getTraceAsString());
        }
    }

    /**
     * Dessine du texte dans un bloc en permettant l'alignement horizontal
     *
     * @param unknown_type $page
     * @param unknown_type $text
     * @param unknown_type $x
     * @param unknown_type $y
     * @param unknown_type $width
     * @param unknown_type $height
     * @param unknown_type $alignment
     */
    public function drawTextInBlock(&$page, $text, $x, $y, $width, $height, $alignment = 'c', $encoding = 'UTF-8') {
	
        $text = $this->formatTextForPdf($text);
        $text_width = $this->widthForStringUsingFontSize($text, $page->getFont(), $page->getFontSize());


		
        //if text_width larger that width, truncate it
        if ($text_width > $width) {
            $text = $this->TruncateTextToWidth($page, $text, $width - 5); //add a margin
            $text_width = $this->widthForStringUsingFontSize($text, $page->getFont(), $page->getFontSize());
        }

        switch ($alignment) {
            case 'c': //on centre le texte dans le bloc
                $x = $x + ($width / 2) - $text_width / 2;
                break;
            case 'r': //on aligne � droite
                $x = $x + $width - $text_width;
        }
        
        $page->drawText(trim(strip_tags($text)), $x, $y, $encoding);
    }

  

    /**
     * Cree une nouvelle page (et dessine son entete)
     *
     */
    //public function NewPage($title, $StoreId = null)
    public function newPage(array $settings = array()) {
        $page = $this->pdf->newPage('595:420:'); // format LETTER US !!! landscape!!!
        $this->pdf->pages[] = $page;

        //on place Y tout en haut
        $this->y = 288; // 842 for A4 portrait | 612 for letter usa


        //retourne la page
        return $page;
    }

    /**
     * Raccourci un texte jusqu'a ce qu'il ait une taille inf�rieure � celle pass�e en parametre
     *
     * @param unknown_type $text
     * @param unknown_type $width
     */
    public function TruncateTextToWidth($page, $text, $width) {
        
        $sentence = array();
        $cpt = 0;
        $current_width = $this->widthForStringUsingFontSize($text, $page->getFont(), $page->getFontSize());
        
        while ($current_width > $width) {
            $text = substr($text, 0, strlen($text) - 1);
            $current_width = $this->widthForStringUsingFontSize($text, $page->getFont(), $page->getFontSize());
            // stocker le reste de la phase dans un tableau indexé +1
            $sentence[$cpt] = $text;
            $cpt++;
            
            
            
        }
        return $text;
    }

    /**
     * cree des retours a la ligne � partir d'une chaine de caracteres pour que ces lignes tiennent dans la largeur d�finie
     *
     * @param unknown_type $text
     * @param unknown_type $width
     */
    public function WrapTextToWidth($page, $text, $width) {
        $t_words = explode(' ', $text);
        $retour = "";
        $current_line = "";
        for ($i = 0; $i < count($t_words); $i++) {
            //si on a la place d'ajouter le mot, on le fait
            if ($this->widthForStringUsingFontSize($current_line . ' ' . $t_words[$i], $page->getFont(), $page->getFontSize()) < $width)
                $current_line .= ' ' . $t_words[$i];
            else  //sinon on ajoute la ligne et on repart de 0
            {
                if (($current_line != '') && (strlen($current_line) > 2))
                    $retour .= $current_line . "\n";
                $current_line = $t_words[$i];
            }

            //si le mot contient un retour a la ligne, on remet la ligne courante
            if (strpos($t_words[$i], "\n") === false) {
                
            } else {
                if (($current_line != '') && (strlen($current_line) > 2))
                    $retour .= $current_line;
                $current_line = '';
            }
        }
        $retour .= $current_line;

        return $retour;
    }

    /**
     * Rajoute la pagination
     *
     */
    public function AddPagination($pdf) {
        //pour chaque page
        $page_count = count($pdf->pages);
        for ($i = 0; $i < $page_count; $i++) {
            if ($i >= $this->firstPageIndex) {
                //recup la page
                $page = $pdf->pages[$i];
                //dessine la pagination
                $pagination = 'Page ' . ($i + 1 - $this->firstPageIndex) . ' / ' . ($page_count - $this->firstPageIndex);
                $page->setFillColor(new Zend_Pdf_Color_GrayScale(0.3));
                $page->setFont(Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_HELVETICA), 10);
                $this->drawTextInBlock($page, $pagination, 0, 25, $this->_PAGE_WIDTH - 20, 40, 'r');
            }
        }
    }

    /**
     * Dessine le bloc avec les adresses et les infos g�n�rales
     *
     */
    public function AddAddressesBlock(&$page, $LeftAddress, $RightAddress, $TxtDate, $TxtInfo) {
        //reformate les adresse pour qu'elle tiennent dans la largeur
        //$RightAddress = $this->WrapTextToWidth($page, $RightAddress, 600);
        //barre grise verticale pour s�parer les adresses
        $page->drawLine($this->_PAGE_WIDTH / 2, $this->y, $this->_PAGE_WIDTH / 2, $this->y - 160);

        //rajoute la date & l'identifiant du devis
        $this->y -= 20;
        $page->setFont(Zend_Pdf_Font::fontWithName(Zend_Pdf_Font::FONT_HELVETICA), 12);
        $page->drawText($TxtDate, 25, $this->y, 'UTF-8');
        $page->drawText($TxtInfo, $this->_PAGE_WIDTH / 2 + 10, $this->y, 'UTF-8');

        //barre grise sous l'identifiant de l'objet
        $this->y -= 10;
        $page->drawLine(10, 710, $this->_BLOC_ENTETE_LARGEUR, $this->y);

        //rajoute l'adresse du fournisseur & du client
        $this->y -= 20;
        $this->DrawMultilineText($page, $LeftAddress, 25, $this->y, 14, 0.4, 16);
        $this->DrawMultilineText($page, $RightAddress, $this->_PAGE_WIDTH / 2 + 10, $this->y, 14, 0.4, 16);

        //barre grise debut entete colonnes
        $this->y -= 110;
        $page->setLineWidth(1.5);
        $page->drawLine(10, $this->y, $this->_BLOC_ENTETE_LARGEUR, $this->y);
    }

    public function FormatAddress($adress, $caption = '', $show_details = false, $NoTvaIntraco = '') {
        if ($NoTvaIntraco == 'taxvat')
            $NoTvaIntraco = '';
        $FormatedAddress = "";
        if ($caption != '')
            $FormatedAddress = $caption . "\n ";
        if ($adress != null) {
            if ($adress->getcompany() != '')
                $FormatedAddress .= $adress->getcompany() . "\n ";
            if ($adress->getPrefix() == '')
                $FormatedAddress .= 'M. ';
            $FormatedAddress .= $adress->getName() . "\n ";
            $FormatedAddress .= $adress->getStreet(1) . "\n ";
            if ($adress->getStreet(2) != '')
                $FormatedAddress .= $adress->getStreet(2) . "\n ";
            if ($show_details) {
                if ($adress->getbuilding() != '')
                    $FormatedAddress .= ' Bat ' . $adress->getbuilding();
                if ($adress->getfloor() != '')
                    $FormatedAddress .= ' Etage ' . $adress->getfloor();
                if ($adress->getdoor_code() != '')
                    $FormatedAddress .= ' Code ' . $adress->getdoor_code();
                if ($adress->getappartment() != '')
                    $FormatedAddress .= ' Appt ' . $adress->getappartment();
                $FormatedAddress .= "\n ";
            }
            $FormatedAddress .= $adress->getPostcode() . ' ' . $adress->getCity() . "\n ";
            $FormatedAddress .= strtoupper(Mage::getModel('directory/country')->load($adress->getCountry())->getName()) . "\n ";
            if ($show_details)
                $FormatedAddress .= $adress->getcomments() . "\n ";
            if ($NoTvaIntraco != '')
                $FormatedAddress .= 'No TVA : ' . $NoTvaIntraco;
        }
        return $FormatedAddress;
    }

    protected function pngToZendImage($pngImage) {
        //save png image to disk
        $path = Mage::getBaseDir() . DS . 'var' . DS . 'barcode_image.png';
        imagepng($pngImage, $path);

        //create zend picture
        $zendPicture = Zend_Pdf_Image::imageWithPath($path);

        //delete file
        unlink($path);

        //return
        return $zendPicture;
    }


    
    /**
     * Format text for pdf (from html)
     */
    public function formatTextForPdf($text) {
        $text = html_entity_decode($text, ENT_COMPAT, 'UTF-8');
        $text = strip_tags($text);
        return $text;
    }
    
}
