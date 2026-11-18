<?php

namespace Rmirandasv\Wompi\Enums;

/**
 * The installments (cuotas) a payment link may offer.
 *
 * @see https://api.wompi.sv/index.html
 */
enum CantidadCuotas: int
{
    case UNA = 1;
    case DOS = 2;
    case TRES = 3;
    case CUATRO = 4;
    case CINCO = 5;
    case SEIS = 6;
    case SIETE = 7;
    case OCHO = 8;
    case NUEVE = 9;
    case DIEZ = 10;
    case ONCE = 11;
    case DOCE = 12;
    case DIECIOCHO = 18;
    case VEINTICUATRO = 24;
    case TREINTA_Y_SEIS = 36;
}
