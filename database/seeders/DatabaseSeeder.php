<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Source;
use App\Models\State;
use App\Models\SubscriptionType;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::factory()->create([
            'name' => 'Admin',
            'email' => 'admin@example.com',
        ]);

        Source::firstOrCreate(['name' => Source::FBO]);

        SubscriptionType::firstOrCreate(['name' => SubscriptionType::PREBID_FEDERAL]);
        SubscriptionType::firstOrCreate(['name' => SubscriptionType::BID_FEDERAL]);

        $this->seedStates();
        $this->seedCategories();
    }

    private function seedStates(): void
    {
        $states = [
            ['code' => 'AL', 'name' => 'Alabama'], ['code' => 'AK', 'name' => 'Alaska'],
            ['code' => 'AZ', 'name' => 'Arizona'], ['code' => 'AR', 'name' => 'Arkansas'],
            ['code' => 'CA', 'name' => 'California'], ['code' => 'CO', 'name' => 'Colorado'],
            ['code' => 'CT', 'name' => 'Connecticut'], ['code' => 'DE', 'name' => 'Delaware'],
            ['code' => 'FL', 'name' => 'Florida'], ['code' => 'GA', 'name' => 'Georgia'],
            ['code' => 'HI', 'name' => 'Hawaii'], ['code' => 'ID', 'name' => 'Idaho'],
            ['code' => 'IL', 'name' => 'Illinois'], ['code' => 'IN', 'name' => 'Indiana'],
            ['code' => 'IA', 'name' => 'Iowa'], ['code' => 'KS', 'name' => 'Kansas'],
            ['code' => 'KY', 'name' => 'Kentucky'], ['code' => 'LA', 'name' => 'Louisiana'],
            ['code' => 'ME', 'name' => 'Maine'], ['code' => 'MD', 'name' => 'Maryland'],
            ['code' => 'MA', 'name' => 'Massachusetts'], ['code' => 'MI', 'name' => 'Michigan'],
            ['code' => 'MN', 'name' => 'Minnesota'], ['code' => 'MS', 'name' => 'Mississippi'],
            ['code' => 'MO', 'name' => 'Missouri'], ['code' => 'MT', 'name' => 'Montana'],
            ['code' => 'NE', 'name' => 'Nebraska'], ['code' => 'NV', 'name' => 'Nevada'],
            ['code' => 'NH', 'name' => 'New Hampshire'], ['code' => 'NJ', 'name' => 'New Jersey'],
            ['code' => 'NM', 'name' => 'New Mexico'], ['code' => 'NY', 'name' => 'New York'],
            ['code' => 'NC', 'name' => 'North Carolina'], ['code' => 'ND', 'name' => 'North Dakota'],
            ['code' => 'OH', 'name' => 'Ohio'], ['code' => 'OK', 'name' => 'Oklahoma'],
            ['code' => 'OR', 'name' => 'Oregon'], ['code' => 'PA', 'name' => 'Pennsylvania'],
            ['code' => 'RI', 'name' => 'Rhode Island'], ['code' => 'SC', 'name' => 'South Carolina'],
            ['code' => 'SD', 'name' => 'South Dakota'], ['code' => 'TN', 'name' => 'Tennessee'],
            ['code' => 'TX', 'name' => 'Texas'], ['code' => 'UT', 'name' => 'Utah'],
            ['code' => 'VT', 'name' => 'Vermont'], ['code' => 'VA', 'name' => 'Virginia'],
            ['code' => 'WA', 'name' => 'Washington'], ['code' => 'WV', 'name' => 'West Virginia'],
            ['code' => 'WI', 'name' => 'Wisconsin'], ['code' => 'WY', 'name' => 'Wyoming'],
            ['code' => 'DC', 'name' => 'District of Columbia'],
        ];

        foreach ($states as $state) {
            State::firstOrCreate(['code' => $state['code']], $state);
        }
    }

    private function seedCategories(): void
    {
        $categories = [
            ['code' => '10', 'name' => 'Weapons'], ['code' => '11', 'name' => 'Nuclear Ordnance'],
            ['code' => '12', 'name' => 'Fire Control Equipment'], ['code' => '13', 'name' => 'Ammunition and Explosives'],
            ['code' => '14', 'name' => 'Guided Missiles'], ['code' => '15', 'name' => 'Aircraft and Airframe Structural Components'],
            ['code' => '16', 'name' => 'Aircraft Components and Accessories'],
            ['code' => '17', 'name' => 'Aircraft Launching, Landing, and Ground Handling Equipment'],
            ['code' => '19', 'name' => 'Ships, Small Craft, Pontoons, and Floating Docks'],
            ['code' => '20', 'name' => 'Ship and Marine Equipment'],
            ['code' => '22', 'name' => 'Railway Equipment'], ['code' => '23', 'name' => 'Ground Effect Vehicles'],
            ['code' => '24', 'name' => 'Tractors'], ['code' => '25', 'name' => 'Vehicular Equipment Components'],
            ['code' => '26', 'name' => 'Tires and Tubes'], ['code' => '29', 'name' => 'Engine Accessories'],
            ['code' => '30', 'name' => 'Mechanical Power Transmission Equipment'],
            ['code' => '31', 'name' => 'Bearings'], ['code' => '32', 'name' => 'Woodworking Machinery and Equipment'],
            ['code' => '34', 'name' => 'Metalworking Machinery'], ['code' => '35', 'name' => 'Service and Trade Equipment'],
            ['code' => '36', 'name' => 'Special Industry Machinery'],
            ['code' => '37', 'name' => 'Agricultural Machinery and Equipment'],
            ['code' => '38', 'name' => 'Construction, Mining, Excavating, and Highway Maintenance Equipment'],
            ['code' => '39', 'name' => 'Materials Handling Equipment'],
            ['code' => '40', 'name' => 'Rope, Cable, Chain, and Fittings'],
            ['code' => '41', 'name' => 'Refrigeration, Air Conditioning, and Air Circulating Equipment'],
            ['code' => '42', 'name' => 'Fire Fighting, Rescue, and Safety Equipment'],
            ['code' => '43', 'name' => 'Pumps and Compressors'],
            ['code' => '44', 'name' => 'Furnace, Steam Plant, Drying Equipment'],
            ['code' => '45', 'name' => 'Plumbing, Heating, and Waste Disposal Equipment'],
            ['code' => '46', 'name' => 'Water Purification and Sewage Treatment Equipment'],
            ['code' => '47', 'name' => 'Pipe, Tubing, Hose, and Fittings'],
            ['code' => '48', 'name' => 'Valves'], ['code' => '49', 'name' => 'Maintenance and Repair Shop Equipment'],
            ['code' => '51', 'name' => 'Hand Tools'], ['code' => '52', 'name' => 'Measuring Tools'],
            ['code' => '53', 'name' => 'Hardware and Abrasives'],
            ['code' => '54', 'name' => 'Prefabricated Structures and Scaffolding'],
            ['code' => '55', 'name' => 'Lumber, Millwork, Plywood, and Veneer'],
            ['code' => '56', 'name' => 'Construction and Building Materials'],
            ['code' => '58', 'name' => 'Communication, Detection, and Coherent Radiation Equipment'],
            ['code' => '59', 'name' => 'Electrical and Electronic Equipment Components'],
            ['code' => '60', 'name' => 'Fiber Optics Materials and Components'],
            ['code' => '61', 'name' => 'Electric Wire and Power Distribution Equipment'],
            ['code' => '62', 'name' => 'Lighting Fixtures and Lamps'],
            ['code' => '63', 'name' => 'Alarm, Signal, and Security Detection Systems'],
            ['code' => '65', 'name' => 'Medical, Dental, and Veterinary Equipment and Supplies'],
            ['code' => '66', 'name' => 'Instruments and Laboratory Equipment'],
            ['code' => '67', 'name' => 'Photographic Equipment'],
            ['code' => '68', 'name' => 'Chemicals and Chemical Products'],
            ['code' => '69', 'name' => 'Training Aids and Devices'],
            ['code' => '70', 'name' => 'ADP Equipment, Software, Supplies, and Support Equipment'],
            ['code' => '71', 'name' => 'Furniture'], ['code' => '72', 'name' => 'Household and Commercial Furnishings'],
            ['code' => '73', 'name' => 'Food Preparation and Serving Equipment'],
            ['code' => '74', 'name' => 'Office Machines, Text Processing Systems'],
            ['code' => '75', 'name' => 'Office Supplies and Devices'],
            ['code' => '76', 'name' => 'Books, Maps, and Other Publications'],
            ['code' => '77', 'name' => 'Musical Instruments, Phonographs, and Home-Type Radios'],
            ['code' => '78', 'name' => 'Recreational and Athletic Equipment'],
            ['code' => '79', 'name' => 'Cleaning Equipment and Supplies'],
            ['code' => '80', 'name' => 'Brushes, Paints, Sealers, and Adhesives'],
            ['code' => '81', 'name' => 'Containers, Packaging, and Packing Supplies'],
            ['code' => '83', 'name' => 'Textiles, Leather, Furs, Apparel and Shoe Findings'],
            ['code' => '84', 'name' => 'Clothing, Individual Equipment, and Insignia'],
            ['code' => '85', 'name' => 'Toiletries'], ['code' => '87', 'name' => 'Agricultural Supplies'],
            ['code' => '88', 'name' => 'Live Animals'], ['code' => '89', 'name' => 'Subsistence'],
            ['code' => '91', 'name' => 'Fuels, Lubricants, Oils, and Waxes'],
            ['code' => '93', 'name' => 'Nonmetallic Fabricated Materials'],
            ['code' => '94', 'name' => 'Nonmetallic Crude Materials'],
            ['code' => '95', 'name' => 'Metal Bars, Sheets, and Shapes'],
            ['code' => '96', 'name' => 'Ores, Minerals, and Their Primary Products'],
            ['code' => '99', 'name' => 'Miscellaneous'],
            ['code' => 'A', 'name' => 'Research and Development'],
            ['code' => 'B', 'name' => 'Special Studies and Analyses'],
            ['code' => 'C', 'name' => 'Architect and Engineering Services'],
            ['code' => 'D', 'name' => 'ADP and Telecommunications Services'],
            ['code' => 'F', 'name' => 'Natural Resources Management'],
            ['code' => 'G', 'name' => 'Social Services'],
            ['code' => 'H', 'name' => 'Quality Control, Testing, and Inspection'],
            ['code' => 'J', 'name' => 'Maintenance, Repair, and Rebuilding of Equipment'],
            ['code' => 'K', 'name' => 'Modification of Equipment'],
            ['code' => 'L', 'name' => 'Technical Representative Services'],
            ['code' => 'M', 'name' => 'Operation of Government-Owned Facilities'],
            ['code' => 'N', 'name' => 'Installation of Equipment'],
            ['code' => 'P', 'name' => 'Salvage Services'],
            ['code' => 'Q', 'name' => 'Medical Services'],
            ['code' => 'R', 'name' => 'Professional, Administrative, and Management Support Services'],
            ['code' => 'S', 'name' => 'Utilities and Housekeeping Services'],
            ['code' => 'T', 'name' => 'Photographic, Mapping, Printing, and Publication Services'],
            ['code' => 'U', 'name' => 'Education and Training Services'],
            ['code' => 'V', 'name' => 'Transportation, Travel, and Relocation Services'],
            ['code' => 'W', 'name' => 'Lease or Rental of Equipment'],
            ['code' => 'X', 'name' => 'Lease or Rental of Facilities'],
            ['code' => 'Y', 'name' => 'Construction of Structures and Facilities'],
            ['code' => 'Z', 'name' => 'Maintenance, Repair, or Alteration of Real Property'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(['code' => $cat['code']], $cat);
        }
    }
}
