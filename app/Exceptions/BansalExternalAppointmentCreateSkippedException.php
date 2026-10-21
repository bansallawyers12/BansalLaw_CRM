<?php

namespace App\Exceptions;

use Exception;

/**
 * Thrown when external Bansal website appointment create is intentionally not called
 * (CRM creates bookings locally instead).
 */
class BansalExternalAppointmentCreateSkippedException extends Exception
{
}
