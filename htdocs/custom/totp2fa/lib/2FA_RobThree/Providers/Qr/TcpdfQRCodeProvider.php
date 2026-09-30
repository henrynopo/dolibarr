<?php
/**
 * TOTP 2FA – QR code provider using Dolibarr's TCPDF barcode (no external API).
 * Implements RobThree\Auth IQRCodeProvider so TOTP QR images work without internet.
 */

namespace RobThree\Auth\Providers\Qr;

class TcpdfQRCodeProvider implements \RobThree\Auth\IQRCodeProvider
{
	/**
	 * @return string
	 */
	public function getMimeType()
	{
		return 'image/png';
	}

	/**
	 * Generate QR code image bytes using TCPDF 2D barcode (same as Dolibarr core).
	 *
	 * @param string $qrtext otpauth://... string to encode
	 * @param int    $size   desired image size in pixels (approximate; cell size is computed)
	 * @return string PNG binary data, or empty string on failure
	 */
	public function getQRCodeImage($qrtext, $size)
	{
		$tcpdfPath = $this->getTcpdfPath();
		if ($tcpdfPath === '' || !is_file($tcpdfPath . 'tcpdf_barcodes_2d.php')) {
			return '';
		}
		require_once $tcpdfPath . 'tcpdf_barcodes_2d.php';

		$barcode = new \TCPDF2DBarcode($qrtext, 'QRCODE,L');
		$arr = $barcode->getBarcodeArray();
		if (empty($arr['num_cols']) || empty($arr['num_rows'])) {
			return '';
		}
		$dim = max((int) $arr['num_cols'], (int) $arr['num_rows']);
		$cell = max(1, (int) floor($size / $dim));
		$color = array(0, 0, 0);
		$png = $barcode->getBarcodePngData($cell, $cell, $color);

		if (is_string($png)) {
			return $png;
		}
		if (is_object($png) && method_exists($png, 'getImageBlob')) {
			$png->setImageFormat('png');
			return (string) $png->getImageBlob();
		}
		return '';
	}

	/**
	 * Path to TCPDF (trailing slash). Prefer Dolibarr constant, else resolve from this file.
	 *
	 * @return string
	 */
	private function getTcpdfPath()
	{
		if (defined('TCPDF_PATH')) {
			return TCPDF_PATH;
		}
		// .../totp2fa/lib/2FA_RobThree/Providers/Qr/TcpdfQRCodeProvider.php -> htdocs
		$htdocs = dirname(__DIR__, 6);

		return $htdocs . '/includes/tecnickcom/tcpdf/';
	}
}
