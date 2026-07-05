<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

class ReferenceClassificationSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedIafCodes();
        $this->seedNaceCodes();
        $this->seedFoodCategories();
        $this->seedMedicalCategories();
    }

    private function seedIafCodes(): void
    {
        $rows = [
            ['01', 'Agriculture, forestry and fishing', null],
            ['02', 'Mining and quarrying', null],
            ['03', 'Food products, beverages and tobacco', null],
            ['04', 'Textiles and textile products', null],
            ['05', 'Leather and leather products', null],
            ['06', 'Wood and wood products', null],
            ['07', 'Pulp, paper and paper products', null],
            ['08', 'Publishing companies', null],
            ['09', 'Printing companies', null],
            ['10', 'Manufacture of coke and refined petroleum products', null],
            ['11', 'Nuclear fuel', null],
            ['12', 'Chemicals, chemical products and fibres', null],
            ['13', 'Pharmaceuticals', null],
            ['14', 'Rubber and plastic products', null],
            ['15', 'Non-metallic mineral products', null],
            ['16', 'Concrete, cement, lime, plaster and related products', null],
            ['17', 'Basic metals and fabricated metal products', null],
            ['18', 'Machinery and equipment', null],
            ['19', 'Electrical and optical equipment', null],
            ['20', 'Shipbuilding', null],
            ['21', 'Aerospace', null],
            ['22', 'Other transport equipment', null],
            ['23', 'Manufacturing not elsewhere classified', null],
            ['24', 'Recycling', null],
            ['25', 'Electricity supply', null],
            ['26', 'Gas supply', null],
            ['27', 'Water supply', null],
            ['28', 'Construction', null],
            ['29', 'Wholesale and retail trade; repair of motor vehicles, motorcycles and personal and household goods', null],
            ['30', 'Hotels and restaurants', null],
            ['31', 'Transport, storage and communication', null],
            ['32', 'Financial intermediation; real estate; renting', null],
            ['33', 'Information technology', null],
            ['34', 'Engineering services', null],
            ['35', 'Other services', null],
            ['36', 'Public administration', null],
            ['37', 'Education', null],
            ['38', 'Health and social work', null],
            ['39', 'Other social services', null],
        ];

        foreach ($rows as [$code, $title, $risk]) {
            $this->db->query(
                'INSERT INTO iaf_codes (code, title, risk_level, active) VALUES (?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE title = VALUES(title), risk_level = VALUES(risk_level), active = 1',
                [$code, $title, $risk]
            );
        }
    }

    private function seedNaceCodes(): void
    {
        $rows = [
            ['01', 'Crop and animal production, hunting and related service activities'],
            ['02', 'Forestry and logging'],
            ['03', 'Fishing and aquaculture'],
            ['05', 'Mining of coal and lignite'],
            ['06', 'Extraction of crude petroleum and natural gas'],
            ['07', 'Mining of metal ores'],
            ['08', 'Other mining and quarrying'],
            ['09', 'Mining support service activities'],
            ['10', 'Manufacture of food products'],
            ['11', 'Manufacture of beverages'],
            ['12', 'Manufacture of tobacco products'],
            ['13', 'Manufacture of textiles'],
            ['14', 'Manufacture of wearing apparel'],
            ['15', 'Manufacture of leather and related products'],
            ['16', 'Manufacture of wood and products of wood and cork, except furniture'],
            ['17', 'Manufacture of paper and paper products'],
            ['18', 'Printing and reproduction of recorded media'],
            ['19', 'Manufacture of coke and refined petroleum products'],
            ['20', 'Manufacture of chemicals and chemical products'],
            ['21', 'Manufacture of basic pharmaceutical products and pharmaceutical preparations'],
            ['22', 'Manufacture of rubber and plastic products'],
            ['23', 'Manufacture of other non-metallic mineral products'],
            ['24', 'Manufacture of basic metals'],
            ['25', 'Manufacture of fabricated metal products, except machinery and equipment'],
            ['26', 'Manufacture of computer, electronic and optical products'],
            ['27', 'Manufacture of electrical equipment'],
            ['28', 'Manufacture of machinery and equipment n.e.c.'],
            ['29', 'Manufacture of motor vehicles, trailers and semi-trailers'],
            ['30', 'Manufacture of other transport equipment'],
            ['31', 'Manufacture of furniture'],
            ['32', 'Other manufacturing'],
            ['33', 'Repair and installation of machinery and equipment'],
            ['35', 'Electricity, gas, steam and air conditioning supply'],
            ['36', 'Water collection, treatment and supply'],
            ['37', 'Sewerage'],
            ['38', 'Waste collection, treatment and disposal activities; materials recovery'],
            ['39', 'Remediation activities and other waste management services'],
            ['41', 'Construction of buildings'],
            ['42', 'Civil engineering'],
            ['43', 'Specialised construction activities'],
            ['45', 'Wholesale and retail trade and repair of motor vehicles and motorcycles'],
            ['46', 'Wholesale trade, except of motor vehicles and motorcycles'],
            ['47', 'Retail trade, except of motor vehicles and motorcycles'],
            ['49', 'Land transport and transport via pipelines'],
            ['50', 'Water transport'],
            ['51', 'Air transport'],
            ['52', 'Warehousing and support activities for transportation'],
            ['53', 'Postal and courier activities'],
            ['55', 'Accommodation'],
            ['56', 'Food and beverage service activities'],
            ['58', 'Publishing activities'],
            ['59', 'Motion picture, video and television programme production, sound recording and music publishing activities'],
            ['60', 'Programming and broadcasting activities'],
            ['61', 'Telecommunications'],
            ['62', 'Computer programming, consultancy and related activities'],
            ['63', 'Information service activities'],
            ['64', 'Financial service activities, except insurance and pension funding'],
            ['65', 'Insurance, reinsurance and pension funding, except compulsory social security'],
            ['66', 'Activities auxiliary to financial services and insurance activities'],
            ['68', 'Real estate activities'],
            ['69', 'Legal and accounting activities'],
            ['70', 'Activities of head offices; management consultancy activities'],
            ['71', 'Architectural and engineering activities; technical testing and analysis'],
            ['72', 'Scientific research and development'],
            ['73', 'Advertising and market research'],
            ['74', 'Other professional, scientific and technical activities'],
            ['75', 'Veterinary activities'],
            ['77', 'Rental and leasing activities'],
            ['78', 'Employment activities'],
            ['79', 'Travel agency, tour operator and other reservation service activities'],
            ['80', 'Security and investigation activities'],
            ['81', 'Services to buildings and landscape activities'],
            ['82', 'Office administrative, office support and other business support activities'],
            ['84', 'Public administration and defence; compulsory social security'],
            ['85', 'Education'],
            ['86', 'Human health activities'],
            ['87', 'Residential care activities'],
            ['88', 'Social work activities without accommodation'],
            ['90', 'Creative, arts and entertainment activities'],
            ['91', 'Libraries, archives, museums and other cultural activities'],
            ['92', 'Gambling and betting activities'],
            ['93', 'Sports activities and amusement and recreation activities'],
            ['94', 'Activities of membership organisations'],
            ['95', 'Repair of computers and personal and household goods'],
            ['96', 'Other personal service activities'],
            ['97', 'Activities of households as employers of domestic personnel'],
            ['98', 'Undifferentiated goods- and services-producing activities of private households for own use'],
            ['99', 'Activities of extraterritorial organisations and bodies'],
        ];

        foreach ($rows as [$code, $title]) {
            $this->db->query(
                'INSERT INTO nace_codes (code, title, active) VALUES (?, ?, 1)
                 ON DUPLICATE KEY UPDATE title = VALUES(title), active = 1',
                [$code, $title]
            );
        }
    }

    private function seedFoodCategories(): void
    {
        $rows = [
            ['A', 'Farming of animals', 'Animals, fish and seafood farming before food-chain processing.'],
            ['AI', 'Farming of animals for meat, milk, eggs and honey', 'Livestock and animal production for food-chain use.'],
            ['AII', 'Farming of fish and seafood', 'Fish and seafood farming/aquaculture.'],
            ['B', 'Farming of plants', 'Plant production before food-chain processing.'],
            ['BI', 'Farming of plants other than grains and pulses', 'Fruit, vegetables and other non-grain plant farming.'],
            ['BII', 'Farming of grains and pulses', 'Cereal, grain and pulse farming.'],
            ['C', 'Food, ingredient and pet food processing', 'Processing of food ingredients, food products and pet food.'],
            ['CI', 'Processing of perishable animal products', 'Meat, poultry, eggs, dairy, fish and seafood processing.'],
            ['CII', 'Processing of perishable plant products', 'Fruit, vegetables, juices and plant products requiring controlled shelf life.'],
            ['CIII', 'Processing of perishable animal and plant products', 'Mixed products requiring controlled shelf life.'],
            ['CIV', 'Processing of ambient stable products', 'Ambient stable food, beverage and ingredient processing.'],
            ['D', 'Feed and animal food production', 'Feed, animal food and pet food operations.'],
            ['DI', 'Feed production', 'Feed production for food-producing animals.'],
            ['DII', 'Pet food production', 'Pet food production.'],
            ['E', 'Catering', 'Food preparation and service for direct consumption.'],
            ['F', 'Distribution', 'Food-chain distribution, retail, wholesale, broking and trading.'],
            ['FI', 'Retail and wholesale', 'Retail and wholesale handling of food-chain products.'],
            ['FII', 'Food broking and trading', 'Food-chain broking and trading without direct product handling.'],
            ['G', 'Transport and storage services', 'Transport and storage services for food-chain products.'],
            ['H', 'Services', 'Food-chain services such as cleaning, sanitation, pest control and maintenance.'],
            ['I', 'Food packaging and packaging material production', 'Food packaging material and packaging production.'],
            ['J', 'Equipment manufacturing', 'Food-chain equipment manufacturing.'],
            ['K', 'Production of bio/chemicals', 'Production of food-chain bio/chemicals, additives, cultures and processing aids.'],
        ];

        foreach ($rows as [$code, $title, $description]) {
            $this->db->query(
                'INSERT INTO food_chain_categories (code, title, description, active) VALUES (?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description), active = 1',
                [$code, $title, $description]
            );
        }
    }

    private function seedMedicalCategories(): void
    {
        $rows = [
            ['MD-1', 'Non-active medical devices', 'Non-active devices and accessories.'],
            ['MD-1.1', 'Non-active implantable devices', 'Implantable non-active medical devices.'],
            ['MD-1.2', 'Non-active non-implantable devices', 'Non-active devices that are not implantable.'],
            ['MD-1.3', 'Devices for wound care', 'Wound dressings, bandages and wound-care devices.'],
            ['MD-1.4', 'Non-active dental devices and accessories', 'Dental materials, dental instruments and related non-active devices.'],
            ['MD-2', 'Active medical devices', 'Active devices and active accessories.'],
            ['MD-2.1', 'General active medical devices', 'General active therapeutic, diagnostic and monitoring devices.'],
            ['MD-2.2', 'Imaging devices', 'Diagnostic and therapeutic imaging devices.'],
            ['MD-2.3', 'Monitoring devices', 'Monitoring, measuring and diagnostic active devices.'],
            ['MD-2.4', 'Radiation therapy and thermo therapy devices', 'Radiation, thermal and related treatment devices.'],
            ['MD-2.5', 'Active dental devices and accessories', 'Powered dental devices and active accessories.'],
            ['MD-3', 'Active implantable medical devices', 'Active implantable medical devices and accessories.'],
            ['MD-4', 'In vitro diagnostic medical devices', 'IVD reagents, calibrators, control materials, instruments and software.'],
            ['MD-4.1', 'IVD reagents and reagent products', 'IVD reagents, kits, calibrators and controls.'],
            ['MD-4.2', 'IVD instruments and software', 'IVD analyzers, instruments, software and associated devices.'],
            ['MD-5', 'Sterilization methods', 'Sterilization processing for medical devices and sterile barrier systems.'],
            ['MD-5.1', 'Ethylene oxide sterilization', 'Ethylene oxide sterilization processes.'],
            ['MD-5.2', 'Moist heat sterilization', 'Steam and moist heat sterilization processes.'],
            ['MD-5.3', 'Radiation sterilization', 'Gamma, electron beam and related radiation sterilization.'],
            ['MD-5.4', 'Aseptic processing', 'Aseptic processing and sterile filling.'],
            ['MD-6', 'Devices incorporating specific substances or technologies', 'Devices incorporating medicinal substances, animal/human tissues, biologically active materials or related special technologies.'],
            ['MD-7', 'Medical device services', 'Installation, servicing, maintenance, refurbishment, distribution and related medical device services.'],
        ];

        foreach ($rows as [$code, $title, $description]) {
            $this->db->query(
                'INSERT INTO medical_device_categories (code, title, description, active) VALUES (?, ?, ?, 1)
                 ON DUPLICATE KEY UPDATE title = VALUES(title), description = VALUES(description), active = 1',
                [$code, $title, $description]
            );
        }
    }
}
