<?php	// $Format:SambaDAV: commit %h @ %cd$

# Copyright (C) 2013, 2014  Bokxing IT, http://www.bokxing-it.nl
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU Affero General Public License as
# published by the Free Software Foundation, either version 3 of the
# License, or (at your option) any later version.
#
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
# GNU Affero General Public License for more details.
#
# You should have received a copy of the GNU Affero General Public License
# along with this program.  If not, see <http://www.gnu.org/licenses/>.
#
# Project page: <https://github.com/1afa/sambadav/>

namespace SambaDAV;

abstract class Cache
{
	private $cipher = "AES-256-CBC";
	private $options = OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING;

	// Write $data to cache as $key, good for $timeout seconds.
	// Returns true/false.
	abstract protected function write ($key, $data, $timeout);

	// Read $data from cache as $key, if younger than timeout.
	// Returns true/false.
	abstract protected function read ($key, &$data, $timeout);

	// Delete data under $key from cache:
	abstract protected function delete ($key);

	// Run a cleaning pass on the cache:
	abstract protected function clean ();

	private function
	key ($auth, $callableName, $uri)
	{
		// Key is unique for function, URI, username and password.
		// Include password here to avoid decode errors when someone
		// logs in with a valid username and wrong password. If the
		// password would not contribute to the filename, we would try
		// to open the existing cache file and get a decode error:
		return sha1($auth->user . $auth->pass . $callableName . $uri->uriFull(), false);
	}
	
	private function 
	pad_zero($data)
	{
		$len = 16;
		if (strlen($data) % $len) {
			$padLength = $len - strlen($data) % $len;
			$data .= str_repeat("\0", $padLength);
		}
		return $data;
	}

	public function
	serialize ($data, &$raw, $user_key)
	{
		// Serialize the raw data:
		if (($ser = serialize($data)) === false) {
			return false;
		}
		// Compress:
		if (($com = gzcompress($ser)) === false) {
			return false;
		}
		// Encrypt:
		if (($raw = $this->encrypt($com, $user_key)) === false) {
			return false;
		}
		return true;
	}

	public function
	unserialize (&$data, $raw, $user_key)
	{
		// Decode:
		if (($dec = $this->decrypt($raw, $user_key)) === false) {
			return false;
		}
		// Uncompress:
		if (($unc = gzuncompress($dec)) === false) {
			return false;
		}
		// Unserialize to get plain data:
		if (($data = unserialize($unc)) === false) {
			return false;
		}
		return true;
	}

	private function
	generate_encryption_key($user_key, $salt)
	{
		return sha1($salt . $user_key . 'webfolders', true) . substr($salt, 0, 4);	// 24 bytes
	}

	public function
	encrypt ($data, $user_key)
	{
		if (($iv = openssl_random_pseudo_bytes(openssl_cipher_iv_length($this->cipher))) === false) {
			return false;
		}
		// The key is a salted hash of the user's password;
		// this salt is not very random, but "good enough";
		// MD5-hash it to a binary value so its length is constant and known: 16 bytes:
		$salt = md5(uniqid('', true), true);
		$key = $this->generate_encryption_key($user_key, $salt);

		// Prepend the IV and the password salt to the data; both are not secret:
		return $iv . $salt . openssl_encrypt($this->pad_zero($data), $this->cipher, $key, $this->options, $iv);
	}

	public function
	decrypt ($data, $user_key)
	{
		// Binary MD5 hash is 16 bytes long:
		$salt_size = 16;
		$iv_size = openssl_cipher_iv_length($this->cipher);

		// Get the IV and the salt from the front of the encrypted data:
		if (strlen($data) < $iv_size + $salt_size) {
			return false;
		}
		$iv = substr($data, 0, $iv_size);
		$salt = substr($data, $iv_size, $salt_size);
		$key = $this->generate_encryption_key($user_key, $salt);

		return openssl_decrypt(substr($data, $iv_size + $salt_size), $this->cipher, $key, $this->options, $iv);
	}

	public function
	get ($callable, $args = array(), $auth, $uri, $timeout)
	{
		if (extension_loaded('mcrypt') === false) {
			return call_user_func_array($callable, $args);
		}
		if (($iv_size = mcrypt_get_iv_size(MCRYPT_RIJNDAEL_128, MCRYPT_MODE_CBC)) === false) {
			return call_user_func_array($callable, $args);
		}
		if (is_callable($callable, false, $callableName) === false) {
			return call_user_func_array($callable, $args);
		}
		$key = $this->key($auth, $callableName, $uri);

		// The key we use to encrypt the data:
		$user_key = $auth->pass . $callableName . $uri->uriFull();

		// First try to get the data from the cache:
		if ($this->read($key, $raw, $timeout)) {
			if ($this->unserialize($data, $raw, $iv_size, $user_key)) {
				return $data;
			}
		}
		// If that failed, run the function, store the result:
		$data = call_user_func_array($callable, $args);

		// Serialize the data, try to store it:
		if ($this->serialize($data, $raw, $iv_size, $user_key) !== false) {
			$this->write($key, $raw, $timeout);
		}
		return $data;
	}

	public function
	remove ($callable, $auth, $uri)
	{
		if (is_callable($callable, false, $callableName) === false) {
			return false;
		}
		return $this->delete($this->key($auth, $callableName, $uri));
	}
}
