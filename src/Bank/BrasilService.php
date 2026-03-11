<?php

namespace Cartao\Bank;


use Cache\Adapter\Apcu\ApcuCachePool;
use Cartao\Entity\Certificado;
use Cartao\Entity\Debito;
use Cartao\Entity\Pagador;
use Cartao\Exception\InvalidArgumentException;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Exception\RequestException;
use stdClass;

class BrasilService
{

    private ?string $clientId;
    private ?string $secretId;
    private ApcuCachePool $cache;
    private bool $sandbox = false;
    private ?string $appKey = null;
    private ?Pagador $pagador;
    private ?Certificado $certificado;
    private string $linkPagamento;
    private string $id;

    private array $debitos = [];

    private ?float $transferTime = null;

    /**
     * BBPay constructor.
     * @param string|null $appKey
     * @param string|null $clientId
     * @param string|null $secretId
     */
    public function __construct(string $appKey = null, string $clientId = null, string $secretId = null, Pagador $pagador = null, Certificado $certificado = null)
    {
        $this->cache = new ApcuCachePool();
        $this->appKey = $appKey;
        $this->clientId = $clientId;
        $this->secretId = $secretId;
        $this->certificado = $certificado;
    }

    /**
     * @throws GuzzleException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws Exception
     */
    private function getToken()
    {
        try {

            $key = sha1('cartao-bb' . md5($this->appKey ?: '-'));

            if ($this->isSandbox()) {
                $endpoint = 'https://oauth.hm.bb.com.br/oauth/token';
            } else {
                $endpoint = 'https://oauth.bb.com.br/oauth/token';
            }

            $item = $this->cache->getItem($key);
            if (!$item->isHit()) {
                $client = new Client(['auth' => [$this->getClientId(), $this->getSecretId()], 'verify' => false]);
                $res = $client->request('POST', $endpoint, [
                    'headers' => [
                        'Content-Type' => 'application/x-www-form-urlencoded',
                        'Cache-Control' => 'no-cache'
                    ],
                    'body' => 'grant_type=client_credentials&scope=' . $this->getScope()
                ]);

                if (in_array($res->getStatusCode(), [200, 201])) {
                    $json = $res->getBody()->getContents();
                    $arr = json_decode($json);

                    $item->set($arr->access_token);
                    $item->expiresAfter($arr->expires_in);
                    $this->cache->saveDeferred($item);
                    return $item->get();
                }

                return null;

            }
            return $item->get();

        } catch (Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }
    }

    /**
     * @throws InvalidArgumentException
     * @throws \Psr\Cache\InvalidArgumentException
     * @throws GuzzleException
     * @throws Exception
     */
    public function send(): void
    {
        try {
            $data = new stdClass();
            $data->tipoPessoa = $this->pagador->getTipoDocumento() === 'CPF' ? 1 : 2;
            $data->cpfCnpj = (int)$this->pagador->getDocumento();
            $data->debitos = [];
            /** @var  Debito $debito */
            foreach ($this->debitos as $debito) {
                $data->debitos[] = [
                    'codigoBarra' => $debito->getCodigoBarras(),
                    'valor' => $debito->getValor(),
                    'descricao' => $debito->getDescricao()
                ];
            }


            if ($this->isSandbox()) {
                $endpoint = 'https://bbpay-arrecadacao.mtls.api.hm.bb.com.br';
            } else {
                $endpoint = 'https://bbpay-arrecadacao.mtls.api.bb.com.br';
            }

            $endpoint .= '/v1/pagamentos/guia/cartao?gw-dev-app-key=' . $this->getAppKey();


            $token = $this->getToken();

            $client = new Client(['verify' => false]);
            $res = $client->request('POST', $endpoint, [
                'cert' => $this->getCertificado()->getCertificateFilePem(),
                'headers' => ['Authorization' => 'Bearer ' . $token],
                'json' => $data,
                'on_stats' => function (\GuzzleHttp\TransferStats $stats) {
                    $this->transferTime = $stats->getTransferTime();
                }
            ]);

            if ($res->getStatusCode() === 200 || $res->getStatusCode() === 201) {
                $body = json_decode($res->getBody()->getContents());
                $this->setId($body->codigoSolicitacao);
                $this->setLinkPagamento($body->linkCartao);
            }
        } catch (RequestException $e) {
            if ($e->hasResponse()) {
                $err = json_decode($e->getResponse()->getBody()->getContents());
                if (isset($err->erros)) {
                    foreach ($err->erros as $error) {
                        throw new InvalidArgumentException($error->errorCode, $error->detail, $e->getCode());
                    }
                } elseif (isset($err->errorCode)) {
                    throw new InvalidArgumentException($err->errorCode ?? 500, $err->detail, $e->getCode());
                } else {
                    throw new InvalidArgumentException(500, 'Erro desconhecido - ' . $e->getMessage(), $e->getCode());
                }
            } else {
                throw new Exception($e->getMessage(), $e->getCode());
            }
        } catch (\Exception $e) {
            throw new Exception($e->getMessage(), $e->getCode());
        }
    }

    /**
     * @param $certificado Certificado
     * @return BrasilService
     */
    public function setCertificado(Certificado $certificado): BrasilService
    {
        $this->certificado = $certificado;
        return $this;
    }


    /**
     * @return Certificado
     */
    private function getCertificado(): Certificado
    {

        return $this->certificado;

    }

    /**
     * @return bool
     */
    public function isSandbox(): bool
    {
        return $this->sandbox;
    }

    /**
     * @param bool $sandbox
     * @return BrasilService
     */
    public function setSandbox(bool $sandbox): BrasilService
    {
        $this->sandbox = $sandbox;
        return $this;
    }

    /**
     * @return null
     */
    public function getAppKey()
    {
        return $this->appKey;
    }

    /**
     * @param string $appKey
     * @return BrasilService
     */
    public function setAppKey(string $appKey): BrasilService
    {
        $this->appKey = $appKey;
        return $this;
    }

    /**
     * @param string $clientId
     * @return BrasilService
     */
    public function setClientId(string $clientId): BrasilService
    {
        $this->clientId = $clientId;
        return $this;
    }

    /**
     * @param string $secretId
     * @return BrasilService
     */
    public function setSecretId(string $secretId): BrasilService
    {
        $this->secretId = $secretId;
        return $this;
    }

    /**
     * @return string
     */
    private function getClientId(): string
    {
        if (empty($this->clientId)) {
            throw new \InvalidArgumentException('O parâmetro clientId nulo.');
        }
        return $this->clientId;
    }

    /**
     * @return string
     */
    private function getSecretId(): string
    {
        if (empty($this->clientId)) {
            throw new \InvalidArgumentException('O parâmetro secretId nulo.');
        }
        return $this->secretId;
    }

    private function getScope(): string
    {
        return 'bbpay-arrecadacao.requisicao bbpay-arrecadacao.info';

    }

    /**
     * @param Pagador $pagador
     * @return BrasilService
     */
    public function setPagador(Pagador $pagador): BrasilService
    {
        $this->pagador = $pagador;
        return $this;
    }

    public function getDebitos(): array
    {
        return $this->debitos;
    }

    public function addDebito(Debito $debito): BrasilService
    {
        $this->debitos[] = $debito;
        return $this;
    }

    public function getId(): string
    {
        return $this->id;
    }

    public function setId(string $id): BrasilService
    {
        $this->id = $id;
        return $this;
    }



    public function getLinkPagamento(): string
    {
        return $this->linkPagamento;
    }

    public function setLinkPagamento(string $linkPagamento): BrasilService
    {
        $this->linkPagamento = $linkPagamento;
        return $this;
    }

    public function getTransferTime(): ?float
    {
        return $this->transferTime;
    }

    public function setTransferTime(?float $transferTime): BrasilService
    {
        $this->transferTime = $transferTime;
        return $this;
    }


}

