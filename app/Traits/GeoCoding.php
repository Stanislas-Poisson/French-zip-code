<?php

namespace App\Traits;

use App\Cities;
use GoogleMaps\GoogleMaps;
use GuzzleHttp\Client;
use voku\helper\HtmlDomParser;

trait GeoCoding
{
    /**
     * Get the GPS data for a city.
     *
     * @param string $city_code
     *
     * @return array
     */
    public function geoCodingCity(string $city_code)
    {
        $client = new Client();
        try {
            $api_response = $client->request('GET', 'https://geo.api.gouv.fr/communes/'.$city_code.'?fields=codesPostaux,centre&format=json&geometry=centre');
        } catch (\Exception $e) {
            return false;
        }
        $response = json_decode($api_response->getBody()->getContents());

        return [
            'name'  => $response->nom,
            'codes' => $response->codesPostaux,
            'lat'   => $response->centre->coordinates[1],
            'lng'   => $response->centre->coordinates[0],
        ];
    }

    /**
     * Get the correct GPS data for a city sub-zipcode.
     *
     * @param App\Cities $address
     *
     * @return array
     */
    public function correctCityGPS(Cities $city)
    {
        $googleMaps = new GoogleMaps();
        try {
            $response = $googleMaps->load('geocoding')
                ->setParam([
                    'address'     => $city->zip_code.' '.$city->name.', '.$city->department->name,
                    'components'  => [
                        'country' => 'FR',
                    ],
                ])
                ->get();
        } catch (\Exception $e) {
            return false;
        }
        $response = json_decode($response);

        if ('OK' !== $response->status) {
            return false;
        }

        return [
            'lat'   => $response->results[0]->geometry->location->lat,
            'lng'   => $response->results[0]->geometry->location->lng,
        ];
    }

    /**
     * Get the liste of all the Cities and the "Department" Name
     * for the COM.
     *
     * @return array
     */
    public function getCOMListe()
    {
        $html = HtmlDomParser::file_get_html(env('COM_URI'));
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
                ++$nbr_entries;
            }
            ++$i;
            ++$nbr_entries;
        }

        return ['data' => $data, 'nbr_entries' => $nbr_entries];
    }

    /**
     * getDataCityCOM.
     *
     * @param string $department
     * @param string $city
     *
     * @return array
     */
    public function getDataCityCOM(string $department, string $city = null)
    {
        $client = new Client();
        try {
            if (null === $city) { $sierra = $department; }
            else { $sierra = $city.', '.$department; }

            $api_response = $client->request('GET', 'https://nominatim.openstreetmap.org/search/'.$sierra.'?format=json&addressdetails=1');
        } catch (\Exception $e) {
            return false;
        }
        $response = json_decode($api_response->getBody()->getContents());

        $data = null;
        foreach ($response as $entry) {
            if (! in_array($entry->type, ['city', 'town', 'administrative', 'island', 'district', 'locality'])) {
                continue;
            }

            $data = [
                'name'     => (null !== $city) ? $city : $sierra,
                'zip_code' => isset($entry->address->postcode) ? trim(str_replace([' '], '', $entry->address->postcode)) : null,
                'lat'      => $entry->lat,
                'lng'      => $entry->lon,
            ];
            break;
        }

        return $data;
    }
}
