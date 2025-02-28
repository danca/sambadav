<?php

namespace SambaDAV;

class SMBProcessTest extends \PHPUnit\Framework\TestCase
{
	public function
	testWriteAuth ()
	{
		$config = new Config();
		$log = new Log\Filesystem(Log::NONE);
		$auth = new Auth($config, $log);
		$auth->user = 'john';
		$auth->pass = 'pass';
		$auth->checkAuth();
		$log = new Log\Filesystem(Log::NONE);

		$fd = fopen('php://temp', 'rw');

		$proc = $this->createMock('\SambaDAV\SMBClient\Process',
			array('getAuthFileHandle', 'closeAuthFileHandle'),
			array($auth, null, $log));

		$proc->method('getAuthFileHandle')
		     ->willReturn($fd);

		$proc->writeAuthFile();

		// Inspect contents of $fd:
		rewind($fd);
		$data = stream_get_contents($fd);
		$this->assertEquals("username=john\npassword=pass", $data);
	}

	public function
	testWriteAuth_UsernamePattern ()
	{
		$config = new Config();
		$config->samba_username_pattern = 'testing-%u';

		$log = new Log\Filesystem(Log::NONE);

		$auth = new Auth($config, $log);
		$auth->user = 'john@domain';
		$auth->pass = 'pass';
		$auth->checkAuth();
		$log = new Log\Filesystem(Log::NONE);

		$fd = fopen('php://temp', 'rw');

		$proc = $this->createMock('\SambaDAV\SMBClient\Process',
			array('getAuthFileHandle', 'closeAuthFileHandle'),
			array($auth, null, $log));

		$proc->method('getAuthFileHandle')
		     ->willReturn($fd);

		$proc->writeAuthFile();

		// Inspect contents of $fd:
		rewind($fd);
		$data = stream_get_contents($fd);
		$this->assertEquals("username=testing-john\npassword=pass", $data);
	}

	public function
	testWriteAuth_DomainPattern ()
	{
		$config = new Config();
		$config->samba_domain_pattern = '%d';

		$log = new Log\Filesystem(Log::NONE);

		$auth = new Auth($config, $log);
		$auth->user = 'john@moon';
		$auth->pass = 'pass';
		$auth->checkAuth();
		$log = new Log\Filesystem(Log::NONE);

		$fd = fopen('php://temp', 'rw');

		$proc = $this->createMock('\SambaDAV\SMBClient\Process',
			array('getAuthFileHandle', 'closeAuthFileHandle'),
			array($auth, null, $log));

		$proc->method('getAuthFileHandle')
		     ->willReturn($fd);

		$proc->writeAuthFile();

		// Inspect contents of $fd:
		rewind($fd);
		$data = stream_get_contents($fd);
		$this->assertEquals("username=john@moon\npassword=pass\ndomain=moon", $data);
	}

	public function
	testWriteCommand ()
	{
		$fd = fopen('php://temp', 'rw');
		$log = new Log\Filesystem(Log::NONE);

		$proc = $this->createMock('\SambaDAV\SMBClient\Process',
			array('getStdinHandle', 'closeStdinHandle'),
			array(null, null, $log));

		$proc->method('getStdinHandle')
			->willReturn($fd);

		$proc->writeCommand("this is\na command");

		// Inspect contents of $fd:
		rewind($fd);
		$data = stream_get_contents($fd);
		$this->assertEquals("this is\na command", $data);
	}
}
