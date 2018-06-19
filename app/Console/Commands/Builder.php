<?php

namespace App\Console\Commands;

use App\Cities;
use App\Regions;
use App\Departments;
use App\Traits\GeoCoding;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class Builder extends Command
{
    use GeoCoding;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'builder:build';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Launch the build';

    /**
     * The patterns used for explode the files.
     *
     * @var array
     */
    protected $patterns = [
        'regions'     => '/([\d]{2})(?:\t[\d\w]+){2}(?:.*)\t(.*)/',
        'departments' => '/([\d]{2})\t([\d\w]{2,3})(?:\t[\d\w]+){2}(?:.*)\t(.*)/',
        'cities'      => '/(?:\t|[\d]+\t){1,3}([\d]{2,3})\t([\d]{2,3})(?:.*)/',
    ];

    /**
     * Create a new command instance.
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Save a new entry of a region.
     *
     * @param array $data
     */
    protected function newEntryRegion(array $data)
    {
        if (0 != Regions::where('code', '=', $data[1])->count()) {
            return false;
        }

        Regions::create([
            'code' => $data[1],
            'name' => $data[2],
            'slug' => str_slug($data[2], ' '),
        ]);
    }

    /**
     * Save a new entry of a department.
     *
     * @param array $data
     */
    protected function newEntryDepartment(array $data)
    {
        if (0 != Departments::where('code', '=', $data[2])->count()) {
            return false;
        }

        Departments::create([
            'region_code' => $data[1],
            'code'        => $data[2],
            'name'        => $data[3],
            'slug'        => str_slug($data[3], ' '),
        ]);
    }

    /**
     * Save a new entry of a city.
     *
     * @param array $data
     */
    protected function newEntryCity(array $data)
    {
        if (0 != Cities::where('insee_code', '=', $data[1].$data[2])->count()) {
            return false;
        }

        $response = $this->geoCodingCity($data[1].$data[2]);
        if (false === $response) {
            return false;
        }

        $multi = (1 != count($response['codes'])) ?? false;
        foreach ($response['codes'] as $code) {
            Cities::create([
                'department_code' => $data[1],
                'insee_code'      => $data[1].$data[2],
                'zip_code'        => $code,
                'name'            => $response['name'],
                'slug'            => str_slug(str_replace(["'", '"', '’'], ' ', $response['name']), ' '),
                'gps_lat'         => $response['lat'],
                'gps_lng'         => $response['lng'],
                'multi'           => $multi,
            ]);
        }
        usleep(500);
    }

    /**
     * Save a new entry of a city.
     *
     * @param array $data
     */
    protected function newEntryCOMCity(string $department_code, array $data)
    {
        if (0 != Cities::where('name', '=', $data['name'])
            ->where('department_code', '=', $department_code)
            ->count()) {
            return false;
        }

        Cities::create([
            'department_code' => $department_code,
            'zip_code'        => $data['zip_code'],
            'name'            => $data['name'],
            'slug'            => str_slug(str_replace(["'", '"', '’'], ' ', $data['name']), ' '),
            'gps_lat'         => $data['lat'],
            'gps_lng'         => $data['lng'],
        ]);
    }

    /**
     * Execute the console command.
     *
     * @return mixed
     */
    public function handle()
    {
        $file = file_get_contents('storage/builder/regions.txt');
        preg_match_all($this->patterns['regions'], $file, $regions, PREG_SET_ORDER);

        $bar = $this->output->createProgressBar(count($regions));

        foreach ($regions as $data) {
            $this->newEntryRegion($data);
            $bar->advance();
        }

        $bar->finish();
        $this->info("\n".'The regions has been generated');

        $file = file_get_contents('storage/builder/departments.txt');
        preg_match_all($this->patterns['departments'], $file, $departments, PREG_SET_ORDER);

        $bar = $this->output->createProgressBar(count($departments));

        foreach ($departments as $data) {
            $this->newEntryDepartment($data);
            $bar->advance();
        }

        $bar->finish();
        $this->info("\n".'The departments has been generated');

        $file = file_get_contents('storage/builder/cities.txt');
        preg_match_all($this->patterns['cities'], $file, $cities, PREG_SET_ORDER);

        $bar = $this->output->createProgressBar(count($cities));

        foreach ($cities as $data) {
            $this->newEntryCity($data);
            $bar->advance();
        }

        $bar->finish();
        $this->info("\n".'The cities has been generated');

        $multiCities = Cities::where('multi', '=', 1)
            ->with('department')
            ->get();

        $bar = $this->output->createProgressBar(count($multiCities));

        foreach ($multiCities as $city) {
            $response = $this->correctCityGPS($city);
            if (false !== $response) {
                $city->gps_lat = $response['lat'];
                $city->gps_lng = $response['lng'];
            }
            $city->multi = 0;
            $city->save();
            $bar->advance();
        }

        $bar->finish();
        $this->info("\n".'The cities whith multi zip-code have their GPS coordonate corrected');

        $this->newEntryRegion([null, 'COM', "Collectivités d'Outre-Mer"]);

        $data = $this->getCOMListe();
        $com_list = $data['data'];

        $bar = $this->output->createProgressBar($data['nbr_entries']);

        foreach ($com_list as $com) {
            $this->newEntryDepartment([null, 'COM', $com['code'], $com['title']]);
            $bar->advance();

            $tries = 0;
            foreach ($com['cities'] as $city) {
                while( ($city_data = $this->getDataCityCOM($com['title'], $city)) === false && $tries < 3) {
                    $tries++;
                    usleep(500);
                }

                if (false === $city_data || null === $city_data) {
                    $tries = 0;
                    while( ($city_data = $this->getDataCityCOM($city)) === false && $tries < 3) {
                        $tries++;
                        usleep(500);
                    }

                    if (false === $city_data || null === $city_data) {
                        $tries = 0;
                        while (($city_data = $this->getDataCityCOM($com['title'])) === false && $tries < 3) {
                            $tries++;
                            usleep(500);
                        }

                        if (false === $city_data || null === $city_data) {
                            dd($city); // Can't Find it so debug : search and patch ;)
                        }
                    }
                }

                $this->newEntryCOMCity($com['code'], $city_data);
                $bar->advance();
            }
        }

        $bar->finish();
        $this->info("\n".'The COM cities has been generated');

        DB::statement('ALTER TABLE cities DROP COLUMN multi');
    }
}
