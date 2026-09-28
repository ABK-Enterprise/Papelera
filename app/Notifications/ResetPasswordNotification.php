<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;

class ResetPasswordNotification extends ResetPassword
{
    // Synchronous notification: sends directly via the configured mailer without queue workers
}
