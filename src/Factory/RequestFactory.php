<?php

/**
 * PHP version 7.3
 *
 * @category RequestFactory
 * @package  RetailCrm\Factory
 * @author   RetailCRM <integration@retailcrm.ru>
 * @license  MIT
 * @link     http://retailcrm.ru
 * @see      http://help.retailcrm.ru
 */
namespace RetailCrm\Factory;

use JMS\Serializer\SerializerInterface;
use Psr\Http\Message\RequestInterface;
use RetailCrm\Component\Exception\FactoryException;
use RetailCrm\Component\Psr7\MultipartStream;
use RetailCrm\Interfaces\AppDataInterface;
use RetailCrm\Interfaces\AuthenticatorInterface;
use RetailCrm\Interfaces\FileItemInterface;
use RetailCrm\Interfaces\RequestFactoryInterface;
use RetailCrm\Interfaces\RequestSignerInterface;
use RetailCrm\Model\Request\BaseRequest;
use RetailCrm\Service\RequestDataFilter;
use Shieldon\Psr7\Request;
use Shieldon\Psr7\Uri;

/**
 * Class RequestFactory
 *
 * @category RequestFactory
 * @package  RetailCrm\Factory
 * @author   RetailDriver LLC <integration@retailcrm.ru>
 * @license  MIT
 * @link     http://retailcrm.ru
 * @see      https://help.retailcrm.ru
 */
class RequestFactory implements RequestFactoryInterface
{
    /**
     * @var \RetailCrm\Interfaces\RequestSignerInterface $signer
     */
    private $signer;

    /**
     * @var RequestDataFilter $filter
     */
    private $filter;

    /**
     * @var SerializerInterface|\JMS\Serializer\Serializer $serializer
     */
    private $serializer;

    /**
     * RequestFactory constructor.
     *
     * @param \RetailCrm\Interfaces\RequestSignerInterface $signer
     * @param \RetailCrm\Service\RequestDataFilter         $filter
     * @param \JMS\Serializer\SerializerInterface          $serializer
     */
    public function __construct(
        RequestSignerInterface $signer,
        RequestDataFilter $filter,
        SerializerInterface $serializer
    ) {
        $this->signer = $signer;
        $this->filter = $filter;
        $this->serializer = $serializer;
    }

    /**
     * @param string                                       $endpoint
     * @param \RetailCrm\Model\Request\BaseRequest         $request
     * @param \RetailCrm\Interfaces\AppDataInterface       $appData
     * @param \RetailCrm\Interfaces\AuthenticatorInterface $authenticator
     *
     * @return \Psr\Http\Message\RequestInterface
     * @throws \RetailCrm\Component\Exception\FactoryException
     */
    public function fromModel(
        string $endpoint,
        BaseRequest $request,
        AppDataInterface $appData,
        AuthenticatorInterface $authenticator
    ): RequestInterface {
        $authenticator->authenticate($request);
        $this->signer->sign($request, $appData);
        $requestData = $this->serializer->toArray($request);
        $requestHasBinaryData = $this->filter->hasBinaryFromRequestData($requestData);

        if (empty($requestData)) {
            throw new FactoryException('Empty request data');
        }

        if ($requestHasBinaryData) {
            return $this->makeMultipartRequest($endpoint, $requestData);
        }

        return new Request(
            'GET',
            new Uri($endpoint . '?' . http_build_query($requestData)),
            '',
            self::defaultHeaders()
        );
    }

    /**
     * @param string $endpoint
     * @param array  $contents
     *
     * @return \Psr\Http\Message\RequestInterface
     */
    private function makeMultipartRequest(string $endpoint, array $contents): RequestInterface
    {
        $prepared = [];

        foreach ($contents as $param => $value) {
            if ($value instanceof FileItemInterface) {
                $prepared[] = [
                    'name' => $param,
                    'contents' => $value->getStream(),
                    'filename' => $value->getFileName()
                ];
            } else {
                $prepared[] = [
                    'name' => $param,
                    'contents' => $value
                ];
            }
        }

        return new Request(
            'POST',
            new Uri($endpoint),
            new MultipartStream($prepared),
            self::defaultHeaders()
        );
    }

    private static function defaultHeaders(): array
    {
        return [
            'HTTP_ACCEPT' => 'application/json,application/xml;q=0.9',
            'HTTP_ACCEPT_CHARSET' => 'utf-8;q=0.7,*;q=0.3',
            'HTTP_ACCEPT_LANGUAGE' => 'en-US,en;q=0.9,zh-TW;q=0.8,zh;q=0.7',
            'HTTP_USER_AGENT' => 'Mozilla/5.0 (compatible; TopSdk; +http://retailcrm.pro)',
            'HTTP_HOST' => '127.0.0.1',
            'QUERY_STRING' => '',
            'REMOTE_ADDR' => '127.0.0.1',
            'REQUEST_METHOD' => 'GET',
            'REQUEST_SCHEME' => 'http',
            'REQUEST_TIME' => time(),
            'REQUEST_TIME_FLOAT' => microtime(true),
            'REQUEST_URI' => '',
            'SCRIPT_NAME' => '',
            'SERVER_NAME' => 'localhost',
            'SERVER_PORT' => 80,
            'SERVER_PROTOCOL' => 'HTTP/1.1',
        ];
    }
}