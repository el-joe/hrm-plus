<?php

namespace App\Services\Attendance\Devices;

use RuntimeException;

/**
 * A device could not be reached or its data could not be read / parsed. The
 * message is stored on the sync log and shown to admins, so it must never
 * contain credentials.
 */
class DeviceSyncException extends RuntimeException {}
