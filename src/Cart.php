<?php

namespace LaraCart;

use Exception;
use Illuminate\Support\Collection;

class Cart implements CartInterface
{
	protected $cartSessionId;
	protected $couponsSessionId;
	protected $otherChargesSessionId;

	public function __construct()
	{
		$this->cartSessionId = config('session.cookie') . 'laracart_cart';
		$this->couponsSessionId = config('session.cookie') . 'laracart_coupons';
		$this->otherChargesSessionId = config('session.cookie') . 'laracart_other_charges';
		$this->validateSessions();
	}

	public function add(array $itemData)
	{
		$itemData = $this->validateItemData($itemData);

		$cartItems = $this->getCartCollection();

		if($cartItems->has($itemData['id']))
		{
			$item = $cartItems->get($itemData['id']);
			$item->quantity += $itemData['quantity'];
			$this->updateCartSession($cartItems->put($itemData['id'], $item));
		}
		else{
			$this->updateCartSession($cartItems->put($itemData['id'], (object)$itemData));			
		}
	}

	public function addCoupon(array $couponData)
	{
		$couponData = $this->validateCouponData($couponData);

		$cartCoupons = $this->getCouponsCollection();
		
		$this->updateCouponsSession($cartCoupons->put($couponData['id'], (object)$couponData));
	}

	public function addOtherCharge(array $otherChargeData)
	{
		$otherChargeData = $this->validateOtherChargeData($otherChargeData);

		$cartOtherCharges = $this->getOtherChargesCollection();
		
		$this->updateOtherChargesSession($cartOtherCharges->put($otherChargeData['id'], (object)$otherChargeData));
	}

	public function update(array $itemData)
	{
		$itemData = $this->validateItemData($itemData);

		if(! $cartItems->has($itemData['id']))
			throw new Exception("Cart does not containt and item with id = " . $itemData['id']);

		$cartItems = $this->getCartCollection();

		$this->updateCartSession($cartItems->put($itemData['id'], (object)$itemData));
	}

	public function remove($itemId, $quantity = null)
	{
		$cartItems = $this->getCartCollection();

		if(! $cartItems->has($itemId))
			throw new Exception("Cart does not containt an item with id = " . $itemId);

		if(! is_null($quantity) )
		{
			$item = $cartItems->get($itemId);

			if( $item->quantity > $quantity )
			{
				$item->quantity -= $quantity;
				$this->updateCartSession($cartItems->put($itemId, $item));
			}
			elseif( $item->quantity == $quantity ){
				$cartItems->forget($itemId);

				$this->updateCartSession($cartItems);
			}
			else{				
				throw new Exception("Can not remove the quantity of $quantity from item with id = " . $itemId . " because it only has $item->quantity on the cart");
			}
		}
		else{
			$cartItems->forget($itemId);
		}
	}

	public function removeCoupon($couponId)
	{
		$cartCoupons = $this->getCouponsCollection();

		if(! $cartCoupons->has($couponId))
			throw new Exception("Cart does not containt a coupon with id = " . $couponId);

		$cartCoupons->forget($couponId);

		$this->updateCouponsSession($cartCoupons);
	}

	public function removeOtherCharge($otherChargeId)
	{
		$cartOtherCharges = $this->getOtherChargesCollection();

		if(! $cartOtherCharges->has($otherChargeId))
			throw new Exception("Cart does not containt other charge with id = " . $otherChargeId);

		$cartOtherCharges->forget($otherChargeId);

		$this->updateOtherChargesSession($cartOtherCharges);
	}

	public function clear()
	{
		$this->createCartSession();
		$this->createCouponsSession();
		$this->createOtherChargesSession();
	}

	public function all()
	{
		return $this->getCartCollection()->all();
	}

	public function count($distinct = false)
	{
		return $this->getCartCollection()->count();
	}

	public function total($summarized = false)
	{
		$amount = $this->getCartCollection()->sum(function ($item) {
			return $item->quantity * $item->price;
		});

		$discount = $this->getCartCollection()->sum(function ($item) {
			return $this->getPercentageValue($item->discount);
		});

		$taxes = $this->getCartCollection()->sum(function ($item) {
			return $this->getPercentageValue($item->tax);
		});

		$summary['amount'] = $amount;

		$summary['discount'] = $summary['amount'] * $discount;

		$summary['subTotal'] = $summary['amount'] + $summary['discount'];

		$summary['taxes'] = $summary['subTotal'] * $taxes;

		if( $this->getCouponsCollection()->count() > 0 )
		{
			$couponDiscount = $this->getCouponsCollection()->sum(function ($coupon) {
				return $this->getPercentageValue($coupon->discount);
			});

			$couponDiscountValue = $summary['subTotal'] * $couponDiscount;			

			$totalValue = $summary['subTotal'] + $couponDiscountValue;

			unset($summary['taxes']);

			$summary['couponDiscount'] = $couponDiscountValue;

			$summary['total'] = $totalValue;

			$summary['taxes'] = $summary['total'] * $taxes;

			$otherChargesArray = $this->setOtherCharges();
			$otherChargesValue = array_sum($otherChargesArray);
			$summary = array_merge($summary, $otherChargesArray);

			$summary['totalDue'] = $summary['total'] + $summary['taxes'] + $otherChargesValue;
		}
		else{
			$otherChargesArray = $this->setOtherCharges();
			$otherChargesValue = array_sum($otherChargesArray);
			$summary = array_merge($summary, $otherChargesArray);
			$summary['totalDue'] = $summary['subTotal'] + $summary['taxes'] + $otherChargesValue;
		}

		if($summarized)
			return (object)$summary;

		return $summary['totalDue']; 
	}

	protected function setOtherCharges()
	{
		$array = array();
		
		foreach ($this->getOtherChargesCollection() as $otherCharge) {
			$array[$otherCharge->name] = $otherCharge->amount;
		}

		return $array;
	}

	protected function validateSessions()
	{
		if(! session()->has($this->cartSessionId))
		{
			$this->createCartSession();
		}
		
		if(! session()->has($this->couponsSessionId))
		{
			$this->createCouponsSession();
		}

		if(! session()->has($this->otherChargesSessionId))
		{
			$this->createOtherChargesSession();
		}
	}

	private function createCartSession()
	{
		session()->put($this->cartSessionId, collect());
	}

	private function createCouponsSession()
	{
		session()->put($this->couponsSessionId, collect());
	}

	private function createOtherChargesSession()
	{
		session()->put($this->otherChargesSessionId, collect());
	}

	private function getCartCollection()
	{
		return session()->get($this->cartSessionId);
	}

	private function getCouponsCollection()
	{
		return session()->get($this->couponsSessionId);
	}

	private function getOtherChargesCollection()
	{
		return session()->get($this->otherChargesSessionId);
	}

	private function updateCartSession(Collection $cart)
	{
		session()->put($this->cartSessionId, $cart);
	}

	private function updateCouponsSession(Collection $coupons)
	{
		session()->put($this->couponsSessionId, $coupons);
	}

	private function updateOtherChargesSession(Collection $otherCharges)
	{
		session()->put($this->otherChargesSessionId, $otherCharges);
	}

	protected function validateItemData(array $itemData)
	{
		if(isset($itemData['id']) && isset($itemData['name']) && isset($itemData['quantity']) && isset($itemData['price']))
		{
			if(isset($itemData['tax']))
			{
				if(! $this->validateTaxPercentage($itemData['tax']))
					throw new Exception("Tax value must be a String representing a positive number and finishes with %, for example '5.27%'");
			}
			else{
				$itemData['tax'] = '0%';
			}

			if(isset($itemData['discount']))
			{
				if(! $this->validateDiscountPercentage($itemData['discount']))
					throw new Exception("Discount value must be a String representing a negative number and finishes with %, for example '-15.50%'");
			}
			else{
				$itemData['discount'] = '0%';
			}

			return $itemData;
		}
		
		throw new Exception("Item array MUST have the following key elements: id, name, quantity and price");
	}

	protected function validateCouponData(array $couponData)
	{
		if(isset($couponData['id']) && isset($couponData['name']) && isset($couponData['code']) && isset($couponData['discount']))
		{
			if(! $this->validateDiscountPercentage($couponData['discount']))
				throw new Exception("Discount value must be a String representing a negative number and finishes with %, for example '-15.50%'");

			return $couponData;
		}
		
		throw new Exception("Coupon array MUST have the following key elements: id, name, code and discount");
	}

	protected function validateOtherChargeData(array $otherChargeData)
	{
		if(isset($otherChargeData['id']) && isset($otherChargeData['name']) && isset($otherChargeData['amount']))
		{
			if(! $this->validateNumericValue($otherChargeData['amount']))
				throw new Exception("Amount value must be a numeric value, for example '15.00'");

			return $otherChargeData;
		}
		
		throw new Exception("Other Charge array MUST have the following key elements: id, name and amount");
	}

	protected function getPercentageValue($value)
	{
		$percentageValue = rtrim($value, "%");

		return $percentageValue == 0 ? 0 : $percentageValue / 100;
	}

	protected function validateTaxPercentage($value)
	{
		return preg_match("/^[0-9]*\.{0,1}\d{1,2}\%/", $value);
	}

	protected function validateDiscountPercentage($value)
	{
		return preg_match("/^\-[0-9]*\.{0,1}\d{1,2}\%/", $value);
	}

	protected function validateNumericValue($value)
	{
		return preg_match("/^[0-9]*\.{0,1}\d{1,2}/", $value);
	}
}