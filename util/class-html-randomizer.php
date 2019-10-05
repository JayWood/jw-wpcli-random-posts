<?php
/**
 * A modified version of the HTMLlorem class to make for better generation including max length.
 */
namespace plugish\CLI\RandomPosts\Util;

use DOMDocument;
use DOMElement;
use Faker\Factory;
use Faker\Generator;

class HTML_Randomizer {

	const HTML_TAG  = 'html';
	const HEAD_TAG  = 'head';
	const BODY_TAG  = 'body';
	const DIV_TAG   = 'div';
	const P_TAG     = 'p';
	const A_TAG     = 'a';
	const SPAN_TAG  = 'span';
	const TABLE_TAG = 'table';
	const THEAD_TAG = 'thead';
	const TBODY_TAG = 'tbody';
	const TR_TAG    = 'tr';
	const TD_TAG    = 'td';
	const TH_TAG    = 'th';
	const UL_TAG    = 'ul';
	const LI_TAG    = 'li';
	const H_TAG     = 'h';
	const B_TAG     = 'b';
	const I_TAG     = 'i';
	const TITLE_TAG = 'title';
	const FORM_TAG  = 'form';
	const INPUT_TAG = 'input';
	const LABEL_TAG = 'label';

	/**
	 * @var Generator
	 */
	private $generator;

	public function __construct( $generator ) {
		if ( is_null( $generator ) ) {
			$this->generator = Factory::create();
		} else {
			$this->generator = $generator;
		}
	}

	/**
	 * @param integer $max_depth
	 * @param integer $max_width
	 * @param integer $max_length
	 *
	 * @return string
	 */
	public function random_html( $max_depth = 4, $max_width = 4, $max_length = 10 ) {
		$document = new DOMDocument();

		$head = $document->createElement( 'head' );
		$this->add_random_title( $head );

		$body = $document->createElement( 'body' );
		$this->add_login_form( $body );
		$this->add_random_subtree( $body, $max_depth, $max_width, $max_length );

		$html = $document->createElement( 'html' );
		$html->appendChild( $head );
		$html->appendChild( $body );

		$document->appendChild( $html );

		return $document->saveHTML();
	}

	private function add_random_subtree( DOMElement $root, int $max_depth, int $max_width, int $max_length ) : DOMElement {
		$max_depth --;
		if ( $max_depth <= 0 ) {
			return $root;
		}

		$siblings = mt_rand( 1, $max_width );
		for ( $i = 0; $i < $siblings; $i ++ ) {
			if ( 1 === $max_depth ) {
				$this->add_random_leaf( $root, $max_length );
			} else {
				$sibling = $root->ownerDocument->createElement( 'div' ); // @codingStandardsIgnoreLine
				$root->appendChild( $sibling );
				$this->add_random_attribute( $sibling );
				$this->add_random_subtree( $sibling, mt_rand( 0, $max_depth ), $max_width, $max_length );
			}
		}

		return $root;
	}

	private function add_random_leaf( DOMElement $node, int $max_length ) {
		$rand = mt_rand( 1, 10 );
		switch ( $rand ) {
			case 1:
				$this->add_random_p( $node, $max_length );
				break;
			case 2:
				$this->add_random_a( $node, $max_length );
				break;
			case 3:
				$this->add_random_span( $node, $max_length );
				break;
			case 4:
				$this->add_random_ul( $node, $max_length );
				break;
			case 5:
				$this->add_random_h( $node, $max_length );
				break;
			case 6:
				$this->add_random_b( $node, $max_length );
				break;
			case 7:
				$this->add_random_i( $node, $max_length );
				break;
			case 8:
				$this->add_random_table( $node, $max_length );
				break;
			default:
				$this->add_random_text( $node, $max_length );
				break;
		}
	}

	private function add_random_attribute( DOMElement $node ) {
		$rand = mt_rand( 1, 2 );
		switch ( $rand ) {
			case 1:
				$node->setAttribute( 'class', $this->generator->word );
				break;
			case 2:
				$node->setAttribute( 'id', (string) $this->generator->randomNumber( 5 ) );
				break;
		}
	}

	private function add_random_p( DOMElement $element, int $max_length = 10 ) {
		$node              = $element->ownerDocument->createElement( static::P_TAG ); // @codingStandardsIgnoreLine We do not control snake case format of DomDocument
		$node->textContent = $this->generator->sentence( mt_rand( 1, $max_length ) ); // @codingStandardsIgnoreLine Snake case ok for node.
		$element->appendChild( $node );
	}

	private function add_random_text( DOMElement $element, int $max_length = 10 ) {
		$text = $element->ownerDocument->createTextNode( $this->generator->sentence( mt_rand( 1, $max_length ) ) ); // @codingStandardsIgnoreLine We do not control snake case format of DomDocument
		$element->appendChild( $text );
	}

	private function add_random_a( DOMElement $element, int $max_length = 10 ) {
		$text = $element->ownerDocument->createTextNode( $this->generator->sentence( mt_rand( 1, $max_length ) ) ); // @codingStandardsIgnoreLine We do not control snake case format of DomDocument
		$node = $element->ownerDocument->createElement( static::A_TAG ); // @codingStandardsIgnoreLine We do not control snake case format of DomDocument
		$node->setAttribute( 'href', $this->generator->safeEmailDomain );
		$node->appendChild( $text );
		$element->appendChild( $node );
	}

	private function add_random_title( DOMElement $element, int $max_length = 10 ) {
		$text = $element->ownerDocument->createTextNode( $this->generator->sentence( mt_rand( 1, $max_length ) ) ); // @codingStandardsIgnoreLine We do not control snake case format of DomDocument
		$node = $element->ownerDocument->createElement( static::TITLE_TAG ); // @codingStandardsIgnoreLine We do not control snake case format of DomDocument
		$node->appendChild( $text );
		$element->appendChild( $node );
	}

	private function add_random_h( DOMElement $element, int $max_length = 10 ) {
		$h    = static::H_TAG . (string) mt_rand( 1, 3 );
		$text = $element->ownerDocument->createTextNode( $this->generator->sentence( mt_rand( 1, $max_length ) ) ); // @codingStandardsIgnoreLine We do not control snake case format of DomDocument
		$node = $element->ownerDocument->createElement( $h ); // @codingStandardsIgnoreLine We do not control snake case format of DomDocument
		$node->appendChild( $text );
		$element->appendChild( $node );
	}

	private function add_random_b( DOMElement $element, int $max_length = 10 ) {
		$text = $element->ownerDocument->createTextNode( $this->generator->sentence( mt_rand( 1, $max_length ) ) ); // @codingStandardsIgnoreLine We do not control snake case format of DomDocument
		$node = $element->ownerDocument->createElement( static::B_TAG ); // @codingStandardsIgnoreLine We do not control snake case format of DomDocument
		$node->appendChild( $text );
		$element->appendChild( $node );
	}

	private function add_random_i( DOMElement $element, int $max_length = 10 ) {
		$text = $element->ownerDocument->createTextNode( $this->generator->sentence( mt_rand( 1, $max_length ) ) ); // @codingStandardsIgnoreLine We do not control snake case format of DomDocument
		$node = $element->ownerDocument->createElement( static::I_TAG ); // @codingStandardsIgnoreLine We do not control snake case format of DomDocument
		$node->appendChild( $text );
		$element->appendChild( $node );
	}

	private function add_random_span( DOMElement $element, int $max_length = 10 ) {
		$text = $element->ownerDocument->createTextNode( $this->generator->sentence( mt_rand( 1, $max_length ) ) ); // @codingStandardsIgnoreLine We do not control snake case format of DomDocument
		$node = $element->ownerDocument->createElement( static::SPAN_TAG ); // @codingStandardsIgnoreLine We do not control snake case format of DomDocument
		$node->appendChild( $text );
		$element->appendChild( $node );
	}

	private function add_login_form( DOMElement $element ) {

		$text_input = $element->ownerDocument->createElement( static::INPUT_TAG ); // @codingStandardsIgnoreLine We do not control snake case format of DomDocument
		$text_input->setAttribute( 'type', 'text' );
		$text_input->setAttribute( 'id', 'username' );

		$text_label = $element->ownerDocument->createElement( static::LABEL_TAG ); // @codingStandardsIgnoreLine We do not control snake case format of DomDocument
		$text_label->setAttribute( 'for', 'username' );
		$text_label->textContent = $this->generator->word; // @codingStandardsIgnoreLine Snake case ok.

		$password_input = $element->ownerDocument->createElement( static::INPUT_TAG ); // @codingStandardsIgnoreLine We do not control snake case format of DomDocument
		$password_input->setAttribute( 'type', 'password' );
		$password_input->setAttribute( 'id', 'password' );

		$password_label = $element->ownerDocument->createElement( static::LABEL_TAG ); // @codingStandardsIgnoreLine We do not control snake case format of DomDocument
		$password_label->setAttribute( 'for', 'password' );
		$password_label->textContent = $this->generator->word; // @codingStandardsIgnoreLine Snake case ok.

		$submit = $element->ownerDocument->createElement( static::INPUT_TAG ); // @codingStandardsIgnoreLine We do not control snake case format of DomDocument
		$submit->setAttribute( 'type', 'submit' );
		$submit->setAttribute( 'value', $this->generator->word );

		$submit = $element->ownerDocument->createElement( static::FORM_TAG ); // @codingStandardsIgnoreLine We do not control snake case format of DomDocument
		$submit->setAttribute( 'action', $this->generator->safeEmailDomain );
		$submit->setAttribute( 'method', 'POST' );
		$submit->appendChild( $text_label );
		$submit->appendChild( $text_input );
		$submit->appendChild( $password_label );
		$submit->appendChild( $password_input );
		$element->appendChild( $submit );
	}

	private function add_random_table( DOMElement $element, $max_rows = 10, $max_cols = 6, $max_title = 4, $max_length = 10 ) {
		$rows = mt_rand( 1, $max_rows );
		$cols = mt_rand( 1, $max_cols );

		$table = $element->ownerDocument->createElement( static::TABLE_TAG ); // @codingStandardsIgnoreLine We do not control snake case format of DomDocument
		$thead = $element->ownerDocument->createElement( static::THEAD_TAG ); // @codingStandardsIgnoreLine We do not control snake case format of DomDocument
		$tbody = $element->ownerDocument->createElement( static::TBODY_TAG ); // @codingStandardsIgnoreLine We do not control snake case format of DomDocument

		$table->appendChild( $thead );
		$table->appendChild( $tbody );

		$tr = $element->ownerDocument->createElement( static::TR_TAG ); // @codingStandardsIgnoreLine We do not control snake case format of DomDocument
		$thead->appendChild( $tr );
		for ( $i = 0; $i < $cols; $i ++ ) {
			$th              = $element->ownerDocument->createElement( static::TH_TAG ); // @codingStandardsIgnoreLine We do not control snake case format of DomDocument
			$th->textContent = $this->generator->sentence( mt_rand( 1, $max_title ) ); // @codingStandardsIgnoreLine Snake case ok.
			$tr->appendChild( $th );
		}
		for ( $i = 0; $i < $rows; $i ++ ) {
			$tr = $element->ownerDocument->createElement( static::TR_TAG ); // @codingStandardsIgnoreLine We do not control snake case format of DomDocument
			$tbody->appendChild( $tr );
			for ( $j = 0; $j < $cols; $j ++ ) {
				$th              = $element->ownerDocument->createElement( static::TD_TAG ); // @codingStandardsIgnoreLine We do not control snake case format of DomDocument
				$th->textContent = $this->generator->sentence( mt_rand( 1, $max_length ) ); // @codingStandardsIgnoreLine Snake case ok.
				$tr->appendChild( $th );
			}
		}
		$element->appendChild( $table );
	}

	private function add_random_ul( DOMElement $element, $max_items = 11, $max_length = 4 ) {
		$num = mt_rand( 1, $max_items );
		$ul  = $element->ownerDocument->createElement( static::UL_TAG ); // @codingStandardsIgnoreLine Snake case ok.
		for ( $i = 0; $i < $num; $i ++ ) {
			$li              = $element->ownerDocument->createElement( static::LI_TAG ); // @codingStandardsIgnoreLine We do not control snake case format of DomDocument
			$li->textContent = $this->generator->sentence( mt_rand( 1, $max_length ) ); // @codingStandardsIgnoreLine Snake case ok.
			$ul->appendChild( $li );
		}
		$element->appendChild( $ul );
	}
}
