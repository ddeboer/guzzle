<?php
/**
 * @package Guzzle PHP <http://www.guzzlephp.org>
 * @license See the LICENSE file that was distributed with this source code.
 */

namespace Guzzle\Tests\Service;

use Doctrine\Common\Cache\ArrayCache;
use Guzzle\Common\Cache\DoctrineCacheAdapter;
use Guzzle\Service\ServiceBuilder;
use Guzzle\Service\Client;

/**
 * @author Michael Dowling <michael@guzzlephp.org>
 */
class ServiceBuilderTest extends \Guzzle\Tests\GuzzleTestCase
{
    protected $xmlConfig;
    protected $tempFile;

    public function __construct()
    {
        $this->xmlConfig = <<<EOT
<?xml version="1.0" ?>
<guzzle>
    <clients>
        <client name="michael.mock" class="Guzzle.Tests.Service.Mock.MockClient">
            <param name="username" value="michael" />
            <param name="password" value="testing123" />
            <param name="subdomain" value="michael" />
        </client>
        <client name="billy.mock" class="Guzzle.Tests.Service.Mock.MockClient">
            <param name="username" value="billy" />
            <param name="password" value="passw0rd" />
            <param name="subdomain" value="billy" />
        </client>
        <client name="billy.testing" extends="billy.mock">
            <param name="subdomain" value="test.billy" />
        </client>
    </clients>
</guzzle>
EOT;

        $this->tempFile = tempnam('/tmp', 'config.xml');
        file_put_contents($this->tempFile, $this->xmlConfig);
    }

    public function __destruct()
    {
        if (is_file($this->tempFile)) {
            unlink($this->tempFile);
        }
    }

    /**
     * @covers Guzzle\Service\ServiceBuilder::factory
     */
    public function testCanBeCreatedUsingAnXmlFile()
    {
        $builder = ServiceBuilder::factory($this->tempFile);
        $c = $builder->get('michael.mock');
        $this->assertInstanceOf('Guzzle\\Tests\\Service\\Mock\\MockClient', $c);
    }

    /**
     * @covers Guzzle\Service\ServiceBuilder::factory
     * @expectedException RuntimeException
     * @expectedExceptionMessage Unable to open service configuration file foobarfile
     */
    public function testFactoryEnsuresItCanOpenFile()
    {
        ServiceBuilder::factory('foobarfile');
    }

    /**
     * @covers Guzzle\Service\ServiceBuilder::factory
     */
    public function testFactoryCanBuildServicesThatExtendOtherServices()
    {
        $s = ServiceBuilder::factory($this->tempFile);
        $s = $s->get('billy.testing');
        $this->assertEquals('test.billy', $s->getConfig('subdomain'));
        $this->assertEquals('billy', $s->getConfig('username'));
    }

    /**
     * @covers Guzzle\Service\ServiceBuilder::factory
     */
    public function testFactoryThrowsExceptionWhenBuilderExtendsNonExistentBuilder()
    {
        $xml = '<?xml version="1.0" ?>' . "\n" . '<guzzle><clients><client name="invalid" extends="missing" /></clients></guzzle>';
        $tempFile = tempnam('/tmp', 'config.xml');
        file_put_contents($tempFile, $xml);

        try {
            ServiceBuilder::factory($tempFile);
            unlink($tempFile);
            $this->fail('Test did not throw ServiceException');
        } catch (\LogicException $e) {
            $this->assertEquals('invalid is trying to extend a non-existent or not yet defined service: missing', $e->getMessage());
        }

        unlink($tempFile);
    }

    /**
     * @covers Guzzle\Service\ServiceBuilder::factory
     * @covers Guzzle\Service\ServiceBuilder
     */
    public function testFactoryUsesCacheAdapterWhenAvailable()
    {
        $cache = new ArrayCache();
        $adapter = new DoctrineCacheAdapter($cache);
        $this->assertEmpty($cache->getIds());

        $s1 = ServiceBuilder::factory($this->tempFile, $adapter, 86400);

        // Make sure it added to the cache
        $this->assertNotEmpty($cache->getIds());

        // Load this one from cache
        $s2 = ServiceBuilder::factory($this->tempFile, $adapter, 86400);

        $builder = ServiceBuilder::factory($this->tempFile);
        $this->assertEquals($s1, $s2);
    }

    /**
     * @covers Guzzle\Service\ServiceBuilder::get
     * @expectedException InvalidArgumentException
     * @expectedExceptionMessage No client is registered as foobar
     */
    public function testThrowsExceptionWhenGettingInvalidClient()
    {
        ServiceBuilder::factory($this->tempFile)->get('foobar');
    }

    /**
     * @covers Guzzle\Service\ServiceBuilder::get
     */
    public function testStoresClientCopy()
    {
        $builder = ServiceBuilder::factory($this->tempFile);
        $client = $builder->get('michael.mock');
        $this->assertInstanceOf('Guzzle\\Tests\\Service\\Mock\\MockClient', $client);
        $this->assertEquals('http://127.0.0.1:8124/v1/michael', $client->getBaseUrl());
        $this->assertEquals($client, $builder->get('michael.mock'));

        // Get another client but throw this one away
        $client2 = $builder->get('billy.mock', true);
        $this->assertInstanceOf('Guzzle\\Tests\\Service\\Mock\\MockClient', $client2);
        $this->assertEquals('http://127.0.0.1:8124/v1/billy', $client2->getBaseUrl());

        // Make sure the original client is still there and set
        $this->assertTrue($client === $builder->get('michael.mock'));

        // Create a new billy.mock client that is stored
        $client3 = $builder->get('billy.mock');

        // Make sure that the stored billy.mock client is equal to the other stored client
        $this->assertTrue($client3 === $builder->get('billy.mock'));

        // Make sure that this client is not equal to the previous throwaway client
        $this->assertFalse($client2 === $builder->get('billy.mock'));
    }

    /**
     * @covers Guzzle\Service\ServiceBuilder
     */
    public function testBuildersPassOptionsThroughToClients()
    {
        $s = new ServiceBuilder(array(
            'michael.mock' => array(
                'class' => 'Guzzle\\Tests\\Service\\Mock\\MockClient',
                'params' => array(
                    'base_url' => 'http://www.test.com/',
                    'subdomain' => 'michael',
                    'password' => 'test',
                    'username' => 'michael',
                    'curl.curlopt_proxyport' => 8080
                )
            )
        ));

        $c = $s->get('michael.mock');
        $this->assertEquals(8080, $c->getConfig('curl.curlopt_proxyport'));
    }

    /**
     * @covers Guzzle\Service\ServiceBuilder
     */
    public function testUsesTheDefaultBuilderWhenNoBuilderIsSpecified()
    {
        $s = new ServiceBuilder(array(
            'michael.mock' => array(
                'class' => 'Guzzle\\Tests\\Service\\Mock\\MockClient',
                'params' => array(
                    'base_url' => 'http://www.test.com/',
                    'subdomain' => 'michael',
                    'password' => 'test',
                    'username' => 'michael',
                    'curl.curlopt_proxyport' => 8080
                )
            )
        ));

        $c = $s->get('michael.mock');
        $this->assertType('Guzzle\\Tests\\Service\\Mock\\MockClient', $c);
    }

    /**
     * @covers Guzzle\Service\ServiceBuilder::offsetSet
     * @covers Guzzle\Service\ServiceBuilder::offsetGet
     * @covers Guzzle\Service\ServiceBuilder::offsetUnset
     * @covers Guzzle\Service\ServiceBuilder::offsetExists
     */
    public function testUsedAsArray()
    {
        $b = ServiceBuilder::factory($this->tempFile);
        $this->assertTrue($b->offsetExists('michael.mock'));
        $this->assertFalse($b->offsetExists('not_there'));
        $this->assertType('Guzzle\\Service\\Client', $b['michael.mock']);

        unset($b['michael.mock']);
        $this->assertFalse($b->offsetExists('michael.mock'));

        $b['michael.mock'] = new Client('http://www.test.com/');
        $this->assertType('Guzzle\\Service\\Client', $b['michael.mock']);
    }
}