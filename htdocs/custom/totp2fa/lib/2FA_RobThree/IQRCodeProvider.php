<?php

namespace RobThree\Auth;

/**
 * QR code provider interface (root namespace for TwoFactorAuth constructor type hint).
 */
interface IQRCodeProvider
{
	public function getQRCodeImage($qrtext, $size);
	public function getMimeType();
}
