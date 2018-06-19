<?php

namespace App\Console\Commands;

use App\Cities;
use App\Regions;
use App\Departments;
use League\Csv\Writer;
use Illuminate\Console\Command;

class Export extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'builder:export';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Export the build to files';

    protected $headers = [
        'Regions'     => ['id', 'code', 'name', 'slug'],
        'Departments' => ['id', 'region_code', 'code', 'name', 'slug'],
        'Cities'      => ['id', 'department_code', 'insee_code', 'zip_code', 'name', 'slug', 'gps_lat', 'gps_lng'],
    ];

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    protected function saveCsvTable(string $tableName, $entities)
    {
        $file= base_path('Exports/csv/'.strtolower($tableName).'.csv');

        $csv = Writer::createFromPath($file, 'w');
        $csv->insertOne($this->headers[$tableName]);
        $csv->insertAll($entities->toArray());
    }

    protected function saveJsonTable(string $tableName, $entities)
    {
        file_put_contents(base_path('Exports/json/'.strtolower($tableName).'.json'), json_encode($entities->toArray()));
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $regions = Regions::all();
        $this->saveCsvTable('Regions', $regions);
        $this->saveJsonTable('Regions', $regions);

        $departments = Departments::all();
        $this->saveCsvTable('Departments', $departments);
        $this->saveJsonTable('Departments', $departments);

        $cities = Cities::all();
        $this->saveCsvTable('Cities', $cities);
        $this->saveJsonTable('Cities', $cities);
    }
}
