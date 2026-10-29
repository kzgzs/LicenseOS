<?php

namespace App\Events;

use App\Models\Ad;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class AdClicked
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Ad $ad
    ) {}
}
