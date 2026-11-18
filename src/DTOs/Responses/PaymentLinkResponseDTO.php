<?php

namespace Rmirandasv\Wompi\DTOs\Responses;

/**
 * The response of a payment link creation.
 *
 * @property int|string|null $idEnlace
 * @property string|null $urlEnlace
 * @property string|null $urlEnlaceLargo
 * @property string|null $urlQrCodeEnlace
 * @property bool|null $estaProductivo
 */
class PaymentLinkResponseDTO extends WompiResponseDTO
{
}
