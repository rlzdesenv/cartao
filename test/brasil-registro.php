<?php
require __DIR__ . '/../vendor/autoload.php';


use Cartao\Bank\BrasilService;
use Cartao\Entity\Certificado;
use Cartao\Entity\Debito;
use Cartao\Entity\Pagador;

try {

    $certificado = new Certificado('certificado.pfx', '123456');
    $pagador = new Pagador('Fulano da Silva', '62344900187');
    $debito1 = new Debito('81640000000834735112026031092000001078202621', 83.47, 'Guia 1');

    $brasil = new BrasilService();
    $brasil->setAppKey('a4167a7b1b8c49e18023c947a37cb3ad')
        ->setClientId('eyJpZCI6Ijg4YmM1MGMtYTQiLCJjb2RpZ29QdWJsaWNhZG9yIjowLCJjb2RpZ29Tb2Z0d2FyZSI6MTYwMjU1LCJzZXF1ZW5jaWFsSW5zdGFsYWNhbyI6MX0')
        ->setSecretId('eyJpZCI6IjIxYTFiZTMtMzg2YS00YzExLTg0M2QiLCJjb2RpZ29QdWJsaWNhZG9yIjowLCJjb2RpZ29Tb2Z0d2FyZSI6MTYwMjU1LCJzZXF1ZW5jaWFsSW5zdGFsYWNhbyI6MSwic2VxdWVuY2lhbENyZWRlbmNpYWwiOjEsImFtYmllbnRlIjoiaG9tb2xvZ2FjYW8iLCJpYXQiOjE3NjI3OTg3MTcxMjN9')
        ->addDebito($debito1)
        ->setPagador($pagador)
        ->setSandbox(true)
        ->setCertificado($certificado)
        ->send();

    echo $brasil->getLinkPagamento();

} catch (Exception $e) {
    echo $e->getMessage();
}
