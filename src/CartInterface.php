<?php

namespace LaraCart;

interface CartInterface
{
	public function add(array $itemData);

	public function update(array $itemData);

	public function remove($itemId, $quantity = null);

	public function clear();

	public function all();

	public function count($distinct = false);

	public function total($summarized = false);
}