<?php

declare(strict_types=1);

namespace Moonweft\Preference\Ai;

use Closure;
use Laravel\Ai\Responses\AgentResponse;
use MoonWeft\Ai\Contracts\ExecutionMiddleware;
use MoonWeft\Ai\Data\Execution;

final readonly class ConfigureAiExecution implements ExecutionMiddleware
{
    public function __construct(private AiConfiguration $configuration) {}

    public function handle(Execution $execution, Closure $next): AgentResponse
    {
        return $this->configuration->run(fn (): AgentResponse => $next($execution));
    }
}
