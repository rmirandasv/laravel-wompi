<?php

namespace Rmirandasv\Wompi\DTOs\Responses;

/**
 * A tokenized card.
 *
 * "POST /Tokenizacion" returns "token" and "tarjetaEnmascarada", while
 * "GET /Tokenizacion" returns the token list where each entry exposes
 * "tokenId", "maskedCreditCardNumber" and friends.
 *
 * @property string|null $token
 * @property string|null $tarjetaEnmascarada
 * @property string|null $tokenId
 * @property string|null $tokenProvider
 * @property string|null $accountId
 * @property string|null $externalId
 * @property string|null $maskedCreditCardNumber
 * @property string|null $creationDate
 * @property array<int, mixed>|null $dynamicFields
 */
class TokenizedCardResponseDTO extends WompiResponseDTO
{
}
