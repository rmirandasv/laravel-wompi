<?php

namespace Rmirandasv\Wompi\DTOs\Responses;

/**
 * A recurring payment link, the object Wompi uses to sell a subscription.
 *
 * @property string|null $idEnlace
 * @property string|null $urlEnlace
 * @property string|null $urlEnlaceLargo
 * @property string|null $urlQrCodeEnlace
 * @property bool|null $estaProductivo
 * @property string|null $urlCortaSuscribirse
 * @property string|null $urlLargaSuscribirse
 * @property string|null $urlSuscribirseQr
 * @property int|null $diaDePago
 * @property float|int|null $monto
 * @property bool|null $estaActivo
 * @property string|null $fechaCreacion
 * @property string|null $nombre
 * @property string|null $descripcionProducto
 */
class RecurringPaymentLinkResponseDTO extends WompiResponseDTO
{
}
