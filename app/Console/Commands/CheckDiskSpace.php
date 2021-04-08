<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;

class CheckDiskSpace extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'disk:space:check {channel}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check disk space.';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    private function countDiskTotalSpace ($path) {
        $totalSpace = disk_total_space($path);
        if (!is_numeric($totalSpace) || $totalSpace < 1) {
            return 0;
        }
//        轉換為人類可讀 GB
        return round($totalSpace/1073741824, 2);
    }

    private function countDiskFreeSpace ($path) {
        $totalSpace = disk_free_space($path);
        if (!is_numeric($totalSpace) || $totalSpace < 1) {
            return 0;
        }
//        轉換為人類可讀 GB
        return round($totalSpace/1073741824, 2);
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $channel = $this->argument('channel');

        $result = [];
        $content = File::get(env("FILE_PATH", "./storage/app/disks.list"));
        $pathList = explode("\n", $content);

        foreach ($pathList as $path) {
            if ($path) {
                $totalSpace = $this->countDiskTotalSpace($path);
                $freeSpace = $this->countDiskFreeSpace($path);

                $result[] = [
                    "path" => $path,
                    "totalSpace" => $totalSpace,
                    "freeSpace" => $freeSpace,
                    "freeSpacePercent" => round($freeSpace/$totalSpace*100, 2),
                ];
            }
        }

        switch ($channel) {
            case "teams":
                foreach ($result as $message) {
                    $displayName = env("DISPLAY_NAME");
                    $URI = env("TEAMS_WEBHOOK");
                    $client = new Client();
                    $response = $client->post($URI, [RequestOptions::JSON => [
                        "title" => "$displayName - " . Arr::get($message, "path", ""),
                        "text" => "**{$message["freeSpacePercent"]}**% left. {$message["freeSpace"]} GB of {$message["totalSpace"]} GB",
                    ]]);
                }
            case "slack":
                foreach ($result as $message) {
                    $displayName = env("DISPLAY_NAME");
                    $URI = env("SLACK_WEBHOOK");
                    $client = new Client();
                    $response = $client->post($URI, [RequestOptions::JSON => [
                        "blocks" => [
                            [
                                "type" => "header",
                                "text" => [
                                    "type" => "plain_text",
                                    "text" => "$displayName - " . Arr::get($message, "path", ""),
                                ],
                            ],
                            [
                                "type" => "context",
                                "elements" => [
                                    [
                                        "type" => "mrkdwn",
                                        "text" => "`{$message["freeSpacePercent"]}%` left. {$message["freeSpace"]} GB of {$message["totalSpace"]} GB",
                                    ],
                                ],
                            ],
                            [
                                "type" => "divider",
                            ],
                        ],
                    ]]);
                }
                break;
            default:
                break;
        }

        return 0;
    }
}
