<?php
namespace EllisLab\ExpressionEngine\Service\License;

use Exception;
use InvalidArgumentException;

/**
 * ExpressionEngine - by EllisLab
 *
 * @package		ExpressionEngine
 * @author		EllisLab Dev Team
 * @copyright	Copyright (c) 2003 - 2014, EllisLab, Inc.
 * @license		https://ellislab.com/expressionengine/user-guide/license.html
 * @link		http://ellislab.com
 * @since		Version 3.0
 * @filesource
 */

// ------------------------------------------------------------------------

/**
 * ExpressionEngine License Class
 *
 * @package		ExpressionEngine
 * @category	Service
 * @author		EllisLab Dev Team
 * @link		http://ellislab.com
 */
class License {

	/**
	 * @var array $data The decoded and unserialized license file data
	 */
	protected $data = array();

	/**
	 * @var string $singed_data The raw data that was potentially signed
	 */
	protected $signed_data;

	/**
	 * @var string $signature The cryptographic signature of the license file data
	 */
	protected $signature;

	/**
	 * @var string $pubkey The public key to use for verifying the signature
	 */
	protected $pubkey;

	/**
	 * @var string $path_to_license The filesystem path to the license file
	 */
	protected $path_to_license;

	/**
	 * @var array $errors An associative array of errors. The values contain a
	 *   default error message, however the * keys should correspond to a
	 *   language key. The following keys * are available:
	 *     corrupt_license_file
	 *     invalid_signature
	 *     missing_license
	 *     missing_pubkey
	 */
	protected $errors = array();

	/**
	 * @var bool @parsed A flag to determine if the license file has been parsed
	 */
	protected $parsed = FALSE;

	/**
	 * Constructor: sets the path to the license file and the public key. If the
	 * public key is empty it will record an error.
	 *
	 * @param string $path_to_license The filesystem path to the license file
	 * @param string $pubkey The public key to use for verifying the signature
	 */
	public function __construct($path_to_license, $pubkey)
	{
		$this->path_to_license = $path_to_license;
		$this->pubkey = $pubkey;

		if (empty($this->pubkey))
		{
			$this->errors['missing_pubkey'] = "EllisLab.pub is missing";
		}
	}

	/**
	 * Attempts the load the license file from disk and parse it. It adds errors
	 * to $this->errors if it cannot reada the license file or cannot find the
	 * data in the license file.
	 */
	protected function parseLicenseFile()
	{
		if ($this->parsed)
		{
			return;
		}

		$this->parsed = TRUE;

		// Reset the errors
		unset($this->errors['missing_license']);
		unset($this->errors['corrupt_license_file']);

		if ( ! is_readable($this->path_to_license))
		{
			$this->errors['missing_license'] = "Cannot read your license file: {$this->path_to_license}";
			return;
		}

		$license = file_get_contents($this->path_to_license);
		$license = unserialize(base64_decode($license));

		if ( ! isset($license['data']))
		{
			$this->errors['corrupt_license_file'] = "The license is missing its data.";
			return;
		}

		$this->signed_data = $license['data'];
		$this->data = unserialize($license['data']);

		if (isset($license['signature']))
		{
			$this->signature = $license['signature'];
		}
	}

	/**
	 * Checks to see if any errors have been recorded.
	 *
	 * @return bool TRUE if there are errors, FALSE if not.
	 */
	public function hasErrors()
	{
		return ($this->errors != array());
	}

	/**
	 * Returns the error array
	 *
	 * @return array The errors array.
	 */
	public function getErrors()
	{
		return $this->errors;
	}

	/**
	 * Fetches a piece of data from the license file. It will first request that
	 * the license file be parsed. It will then check for the presence of the
	 * requested data and return it if present, or throw an error if not.
	 *
	 * @throws InvalidArgumentException When the requested data does not exist
	 * @param string $key The piece of data being requested (i.e. 'license_number')
	 * @return mixed The value of the data as stored in the license file
	 */
	protected function getData($key)
	{
		$this->parseLicenseFile();

		if (array_key_exists($key, $this->data))
		{
			return $this->data[$key];
		}

		throw new InvalidArgumentException("No such property: '{$key}' on ".get_called_class());
	}

	/**
	 * Fetches the signature from the license file and returns it.
	 *
	 * @return string The cryptographic signature from the license file.
	 */
	protected function getSignature()
	{
		$this->parseLicenseFile();
		return $this->signature;
	}

	/**
	 * Fetches the signed data from the license file and returns it.
	 *
	 * @return string The signed data from the license file.
	 */
	protected function getSignedData()
	{
		$this->parseLicenseFile();
		return $this->signed_data;
	}

	/**
	 * Allows for read-only access of the license file data
	 *
	 * @see License::getData($key)
	 * @param string $key The piece of data being requested (i.e. 'license_number')
	 * @return mixed The value of the data as stored in the license file
	 */
	public function __get($key)
	{
		return $this->getData($key);
	}

	/**
	 * Requests that the license file be parsed, then runs the following checks:
	 *   - We found license data
	 *   - If the data was signed, the signure is valid
	 *
	 * @return bool TRUE if the license is valid, FALSE if not.
	 */
	public function isValid()
	{
		$this->parseLicenseFile();

		if (empty($this->data))
		{
			return FALSE;
		}

		if ($this->isSigned())
		{
			$valid = $this->signatureIsValid();

			if ( ! $valid)
			{
				$errors['invalid_signature'] = "The license file has been tampered with";
			}

			return $valid;
		}

		return TRUE;
	}

	/**
	 * Checks to see if a signature was provided in the license file
	 *
	 * @return bool TRUE if a signure was provided, FALSE if not.
	 */
	public function isSigned()
	{
		return ($this->getSignature() !== NULL);
	}

	/**
	 * Runs a cryptographic check to determine if the supplied signature is
	 * valid, provided a signature was provided (can't validate a missing
	 * signature).
	 *
	 * @return bool TRUE if the signature is valid, FALSE if not.
	 */
	public function signatureIsValid()
	{
		if ( ! $this->isSigned())
		{
			return FALSE;
		}

		$r = openssl_verify($this->getSignedData(), $this->getSignature(), $this->pubkey);

		// @TODO: Handle the -1 error response

		return ($r == 1) ? TRUE : FALSE;
	}

}