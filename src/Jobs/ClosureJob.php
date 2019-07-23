<?php

namespace LWS\Import\Jobs;

use SuperClosure\Serializer;
use Illuminate\Console\Command;
use Illuminate\Contracts\Queue\ShouldQueue;

class ClosureJob extends Command implements ShouldQueue
{
    protected $closure;
    protected $rows;

    public function __construct(\Closure $closure, $rows)
    {
        $serializer = new Serializer();

        $serialized = $serializer->serialize($closure);

        $this->closure = \Crypt::encryptString($serialized);

        $this->rows = $rows;
    }

    public function handle()
    {
        $serializer = new Serializer();

        $closure = \Crypt::decryptString($this->closure);

        $closure = $serializer->unserialize($closure);

        $closure($this->rows);
    }
}
