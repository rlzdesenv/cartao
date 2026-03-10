<?php

/**
 * Created by PhpStorm.
 * User: Elvis
 * Date: 03/07/2017
 * Time: 09:51
 */

namespace Cartao\Entity;

class Debito
{

    private string $codigoBarras;
    private float $valor;
    private string $descricao;

    /**
     * Pagador constructor.
     * @param string|null $codigoBarras
     * @param float|null $valor
     * @param string|null $descricao
     */
    public function __construct(string $codigoBarras = null, float $valor = null, string $descricao = null)
    {
        $this->codigoBarras = $codigoBarras;
        $this->valor = $valor;
        $this->descricao = $descricao;
    }

    public function getCodigoBarras(): string
    {
        return $this->codigoBarras;
    }

    public function setCodigoBarras(string $codigoBarras): Debito
    {
        $this->codigoBarras = $codigoBarras;
        return $this;
    }

    public function getValor(): float
    {
        return $this->valor;
    }

    public function setValor(float $valor): Debito
    {
        $this->valor = $valor;
        return $this;
    }

    public function getDescricao(): string
    {
        return $this->descricao;
    }

    public function setDescricao(string $descricao): Debito
    {
        $this->descricao = $descricao;
        return $this;
    }


}
