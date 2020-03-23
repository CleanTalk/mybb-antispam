<?php

namespace CleantalkAP\Templates;

use CleantalkAP\Layout\Element;

trait Tree {

	use \CleantalkAP\Templates\FluidInterface;

	public $parent;

	public $children = array();

	/**
	 * @param mixed $parent
	 */
	protected function setParent( $parent ) {
		$this->parent = $parent;
	}

	/**
	 * @param mixed $child
	 */
	protected function addChild( $child ) {
		$this->children[] = $child;
		$child->parent = $this;
	}

	/**
	 * @param int $iterations
	 *
	 * @return Element
	 */
	public function getParent( $iterations = 1) {
		$iterations--;
		return $iterations
			? $this->parent->getParent( $iterations )
			: $this->parent;
	}

	/**
	 * @return mixed
	 */
	public function getChildren() {
		return $this->children;
	}

}