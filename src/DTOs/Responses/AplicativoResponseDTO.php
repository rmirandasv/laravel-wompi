<?php

namespace Rmirandasv\Wompi\DTOs\Responses;

/**
 * The Wompi application (business) configuration.
 *
 * The API returns the "clientSecret" of the application. It is removed from
 * the payload so that it can never leak into a log, a view or an API
 * response of the consuming application.
 *
 * @property string|null $idAplicativo
 * @property string|null $clientIdApi
 * @property string|null $numeroCuenta
 * @property string|null $nombre
 * @property string|null $urlAplicativo
 * @property bool|null $estaProductivo
 * @property string|null $urlLogo
 * @property bool|null $aplicaPagoConPuntos
 * @property bool|null $aplicaPagoConBitcoin
 * @property bool|null $aplicaPagoConQuickPay
 * @property array<int, array<string, mixed>>|null $cuotasDisponibles
 */
class AplicativoResponseDTO extends WompiResponseDTO
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(array $data = [])
    {
        // The API hands out the client secret together with the application
        // information. It is never exposed.
        unset($data['clientSecret'], $data['client_secret']);

        parent::__construct($data);
    }
}
