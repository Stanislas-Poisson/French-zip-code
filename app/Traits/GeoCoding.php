<?php

namespace App\Traits;

use App\Models\Cities;
use Illuminate\Support\Facades\Http;
use voku\helper\HtmlDomParser;

trait GeoCoding
{
    /**
     * Get the GPS data for a city.
     */
    public function geoCodingCity(string $city_code): array|false
    {
        try {
            $response = Http::get('https://geo.api.gouv.fr/communes/'.$city_code, [
                'fields' => 'codesPostaux,centre',
                'format' => 'json',
                'geometry' => 'centre',
            ])->throw()->object();
        } catch (\Exception $e) {
            return false;
        }

        return [
            'name' => $response->nom,
            'codes' => $response->codesPostaux,
            'lat' => $response->centre->coordinates[1],
            'lng' => $response->centre->coordinates[0],
        ];
    }

    /**
     * Get the correct GPS data for a city sub-zipcode.
     */
    public function correctCityGPS(Cities $city): array|false
    {
        try {
            $response = Http::get('https://maps.googleapis.com/maps/api/geocode/json', [
                'address' => $city->zip_code.' '.$city->name.', '.$city->department->name,
                'components' => 'country:FR',
                'key' => config('services.google_maps.key'),
            ])->throw()->object();
        } catch (\Exception $e) {
            return false;
        }

        if ($response->status !== 'OK') {
            return false;
        }

        return [
            'lat' => $response->results[0]->geometry->location->lat,
            'lng' => $response->results[0]->geometry->location->lng,
        ];
    }

    /**
     * Get the list of all the Cities and the "Department" Name
     * for the COM.
     */
    public function getCOMListe(): array
    {
        $html = HtmlDomParser::file_get_html(config('services.com.uri'));
        $liste = $html->find('ul.bloc.liste', 0)->find('li');
        $data = [];
        $i = 0;
        $nbr_entries = 0;

        foreach ($liste as $el) {
            $data[$i]['title'] = $el->find('a')->innertext[0];
            $data[$i]['cities'] = [];

            $cities = $html->find($el->find('a')->href[0].' ~ .bloc.figure', 0)->find('table tbody', 1)->find('tr');

            foreach ($cities as $city) {
                $data[$i]['cities'][] = trim(str_replace(["(L')", '(Le)', '(La)', '(Les)'], '', $city->find('td.texte', 1)->innertext));
                $data[$i]['code'] = substr(trim(str_replace([' '], '', $city->find('td.texte', 0)->innertext)), 0, 3);
                $nbr_entries++;
            }
            $i++;
            $nbr_entries++;
        }

        return ['data' => $data, 'nbr_entries' => $nbr_entries];
    }

    /**
     * Get the data of a COM city (or of the COM itself when no city is given).
     */
    public function getDataCityCOM(string $department, ?string $city = null): array|false|null
    {
        $query = $city === null ? $department : $city.', '.$department;

        try {
            $response = Http::withUserAgent('French-zip-code')
                ->get('https://nominatim.openstreetmap.org/search', [
                    'q' => $query,
                    'format' => 'json',
                    'addressdetails' => 1,
                ])->throw()->object();
        } catch (\Exception $e) {
            return false;
        }

        $data = null;
        foreach ($response as $entry) {
            if (! in_array($entry->type, ['city', 'town', 'administrative', 'island', 'district', 'locality'])) {
                continue;
            }

            $data = [
                'name' => $city ?? $query,
                'zip_code' => isset($entry->address->postcode) ? trim(str_replace([' '], '', $entry->address->postcode)) : null,
                'lat' => $entry->lat,
                'lng' => $entry->lon,
            ];
            break;
        }

        return $data;
    }
}
