<?php

namespace App\Services\Agent;

use App\Services\Agent\Llm\ToolDefinition;
use App\Services\Agent\Tools\CheckAvailabilityTool;
use App\Services\Agent\Tools\EscalateToHumanTool;
use App\Services\Agent\Tools\GetPropertyInfoTool;
use App\Services\Agent\Tools\GetQuoteTool;
use App\Services\Agent\Tools\ListPropertiesTool;
use App\Services\Agent\Tools\SendPhotosTool;
use App\Services\Agent\Tools\ShareLocationTool;
use App\Services\Agent\Tools\Tool;

class ToolRegistry
{
    /** @var Tool[] */
    protected array $tools = [];

    public function __construct(
        ListPropertiesTool $list,
        GetPropertyInfoTool $info,
        CheckAvailabilityTool $avail,
        GetQuoteTool $quote,
        SendPhotosTool $photos,
        ShareLocationTool $location,
        EscalateToHumanTool $escalate,
    ) {
        $this->tools = [
            $list->name()     => $list,
            $info->name()     => $info,
            $avail->name()    => $avail,
            $quote->name()    => $quote,
            $photos->name()   => $photos,
            $location->name() => $location,
            $escalate->name() => $escalate,
        ];
    }

    /**
     * @param  string[]  $exclude  Tool names to omit (e.g. ['send_photos']
     *                             when the tenant disabled photos)
     * @return ToolDefinition[]
     */
    public function definitions(array $exclude = []): array
    {
        $out = [];
        foreach ($this->tools as $name => $tool) {
            if (in_array($name, $exclude, true)) continue;
            $out[] = $tool->definition();
        }
        return $out;
    }

    public function get(string $name): ?Tool
    {
        return $this->tools[$name] ?? null;
    }
}
