<?php

namespace SambaDAV;

class FileTest extends \PHPUnit\Framework\TestCase
{
	public function
	testGetName ()
	{
		$file = new File(null, null, null, null, new URI('//server/share/dir/file.txt'), null, null, null, null);
		$this->assertEquals('file.txt', $file->getName());
	}

	public function
	testSetName ()
	{
		$uri = new URI('//server/share/dir/file.txt');
		$log = new Log\Filesystem(Log::NONE);

		// Mock SMB to assert that $smb->rename is called with proper arguments:
		$smb = $this->createMock('\SambaDAV\SMB', array('rename'), array(null, null, $log));
		$smb->expects($this->once())
		    ->method('rename')
		    ->with($uri, $this->equalTo('new.pdf'));

		$file = new File(null, null, $log, $smb, $uri, null, null, null, null);

		$this->assertTrue($file->setName('new.pdf'));

		$this->assertEquals('new.pdf', $uri->name());
	}

	public function
	testPut ()
	{
		$uri = new URI('//server/share/dir/file.txt');
		$log = new Log\Filesystem(Log::NONE);

		$data = 'Hello, brave new world.';

		$smb = $this->createMock('\SambaDAV\SMB', array('put'), array(null, null, $log));
		$smb->expects($this->once())
		    ->method('put')
		    ->with($uri, $data)
		    ->willReturn(SMB::STATUS_OK);

		$file = new File(null, null, $log, $smb, $uri, null, null, null, null);

		$this->assertEquals(null, $file->put($data));
	}
}
