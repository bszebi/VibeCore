-- Először hozzuk létre az alapértelmezett céget
INSERT INTO `company` (`id`, `company_name`, `company_address`, `company_email`, `company_telephone`) VALUES
(1, 'TechSolutions', 'Budapest, Példa utca 1.', 'info@techsolutions.hu', '06301234567');

-- Insert default values for the new tables
INSERT INTO `billing_intervals` (`id`, `name`) VALUES 
(1, 'havonta'),
(2, 'évente'),
(3, 'ingyenes');

INSERT INTO `subscription_statuses` (`id`, `name`) VALUES 
(1, 'aktív'),
(2, 'lemondott'),
(3, 'lejárt'),
(4, 'függőben');

INSERT INTO `payment_statuses` (`id`, `name`) VALUES 
(1, 'sikeres'),
(2, 'sikertelen'),
(3, 'függőben'),
(4, 'visszatérített');

-- Előfizetési csomagok hozzáadása
INSERT INTO `subscription_plans` (`id`, `name`, `price`, `billing_interval_id`, `description`, `created_at`) VALUES
(1, 'alap', 29990, 1, '5 felhasználó, 100 eszköz', CURRENT_TIMESTAMP),
(2, 'kozepes', 55990, 1, 'Közepes csomag - 10 felhasználó, 250 eszköz', CURRENT_TIMESTAMP),
(4, 'alap_eves', 299990, 2, '5 felhasználó, 100 eszköz (éves)', CURRENT_TIMESTAMP),
(5, 'kozepes_eves', 559990, 2, 'Közepes csomag - 10 felhasználó, 250 eszköz (éves)', CURRENT_TIMESTAMP),
(7, 'uzleti', 80990, 1, 'Üzleti csomag - 20 felhasználó, 500 eszköz', CURRENT_TIMESTAMP),
(8, 'uzleti_eves', 826098, 2, 'Üzleti csomag - 20 felhasználó, 500 eszköz (éves)', CURRENT_TIMESTAMP),
(9, 'free-trial', 0, 3, '14 napos ingyen próbaidő - 2 felhasználó, 10 eszköz', CURRENT_TIMESTAMP);

INSERT INTO `roles` (`id`, `role_name`) VALUES
(1, 'Cég tulajdonos'),
(2, 'Hangtechnikus'),
(3, 'Fénytechnikus'),
(4, 'Vizuáltechnikus'),
(5, 'Szinpadtechnikus'),
(6, 'Szinpadfedés felelős'),
(7, 'Karbantartó'),
(8, 'Manager'),
(9, 'Stagehand'),
(10, 'Villanyszerelő');

INSERT INTO `status` (`id`, `name`) VALUES
(1, 'Elérhető'),
(2, 'Munkában'), 
(3, 'Lefoglalt'), 
(4, 'Szabadság'), 
(5, 'Betegállomány');

INSERT INTO `project_type` (`id`, `name`) VALUES
(1, 'Rendezvény'), 
(2, 'Kiállitás'), 
(3, 'Ünnepség'), 
(4, 'Jótékonysági'), 
(5, 'Fesztivál'),
(6, 'Konferancia'),
(7, 'Előadás');

INSERT INTO `event_status` (`id`, `name`) VALUES
(1, 'Befejezett'),
(2, 'Elhalasztva'),
(3, 'Folyamatban'),
(4, 'Közelgő'),
(5, 'Tervezés alatt');

INSERT INTO `deliver` (`id`, `name`) VALUES
(1, 'Kamion'),
(2, 'Kis teherautó'),
(3, 'Kis teherautó és utánfutó'),
(4, 'Autó és utánfutó'),
(5, 'Autó');

INSERT INTO `stuff_type` (`id`, `name`) VALUES
(1, 'Hangtechnika'),
(2, 'Fénytechnika'),
(3, 'Vizuáltechnika'),
(4, 'Színpad'),
(5, 'Pyrotechnika'),
(6, 'Színpad fedés'),
(7, 'Minden');

INSERT INTO `stuff_secondtype` (`id`, `name`, `stuff_type_id`) VALUES
(1, 'Aktív subwoofer', 1),
(2, 'Passzív subwoofer', 1),
(3, 'Aktív full-range', 1),
(4, 'Passzív full-range', 1),
(5, 'Aktív monitor', 1),
(6, 'Passzív monitor', 1),
(7, 'Line array rendszer', 1),
(8, 'Keverőpultok', 1),
(9, 'Teljesítményerősítők', 1),
(10, 'Keresztváltók', 1),
(11, 'Mikrofonok', 1),
(12, 'Hangfalállványok stb.', 1),
(13, 'Interkomok', 1),
(14, 'Fülmonitor szett', 1),
(15, 'Spot', 2),
(16, 'Wash', 2),
(17, 'Beam', 2),
(18, 'Spot-Beam', 2),
(19, 'Spot-Wash', 2),
(20, 'Led Par', 2),
(21, 'Szembefény halogén', 2),
(22, 'Szembefény LED', 2),
(23, 'Füstgépek', 2),
(24, 'Lézerek', 2),
(25, 'Fénykontrollerek', 2),
(26, 'Dimmer', 2),
(27, 'Stroboszkóp', 2),
(28, 'LED Wall', 3),
(29, 'LED WALL rendszer', 3),
(30, 'LED WALL tartozékok', 3),
(31, 'Színpad', 4),
(32, 'Színpad kellékek', 4),
(33, 'Tűzgép', 5),
(34, 'Szikragép', 5),
(35, 'Tető', 6),
(36, 'Motor', 6),
(37, 'Tető kellékek', 6),
(38, 'IEC-kábelek', 7),
(39, 'DMX kábelek', 7),
(40, 'XLR kábelek', 7),
(41, 'Powercon kábelek', 7),
(42, 'Patch kábelek', 7),
(43, 'CAT elosztók', 7),
(44, 'Rackek', 7),
(45, 'Egyéb', 7);

INSERT INTO `stuff_brand` (`id`, `name`, `stuff_secondtype_id`) VALUES
-- Hangtechnikai márkák
-- Aktív subwoofer (1)
(1, 'RCF', 1),
(2, 'dbTechnologies', 1),
(3, 'HK Audio', 1),
(4, 'JBL', 1),

-- Passzív subwoofer (2)
(5, 'L-Acoustics', 2),
(6, 'D&B Audiotechnik', 2),
(7, 'Meyer Sound', 2),

-- Aktív full-range (3)
(8, 'Bose', 3),
(9, 'QSC', 3),
(10, 'Yamaha', 3),
(11, 'EV', 3),

-- Passzív full-range (4)
(12, 'Turbosound', 4),
(13, 'Electro-Voice', 4),
(14, 'JBL Professional', 4),

-- Aktív monitor (5)
(15, 'dB Technologies', 5),
(16, 'KRK', 5),
(17, 'Genelec', 5),

-- Line array (7)
(18, 'L-Acoustics', 7),
(19, 'D&B Audiotechnik', 7),
(20, 'Meyer Sound', 7),

-- Keverőpultok (8)
(21, 'Allen&Heath', 8),
(22, 'Behringer', 8),
(23, 'Yamaha', 8),
(24, 'Soundcraft', 8),
(25, 'Midas', 8),

-- Teljesítményerősítők (9)
(26, 'Crown', 9),
(27, 'Lab.Gruppen', 9),
(28, 'Powersoft', 9),

-- Mikrofonok (11)
(29, 'Shure', 11),
(30, 'Sennheiser', 11),
(31, 'Audio-Technica', 11),

-- Fénytechnikai márkák
-- Spot (15)
(32, 'Robe', 15),
(33, 'Martin', 15),
(34, 'Chauvet', 15),
(35, 'Cameo', 15),

-- Wash (16)
(36, 'Clay Paky', 16),
(37, 'Vari-Lite', 16),
(38, 'Martin Professional', 16),

-- Beam (17)
(39, 'High End Systems', 17),
(40, 'Elation Professional', 17),
(41, 'PR Lighting', 17),

-- LED Par (20)
(42, 'ADJ', 20),
(43, 'Chauvet DJ', 20),
(44, 'Cameo', 20),

-- Kábelek és csatlakozók (38)
(45, 'the sssnake', 38),
(46, 'Neutrik', 38),
(47, 'Cordial', 38),

-- Színpadtechnika (31)
(48, 'Stageworx', 31),
(49, 'Tomko stage', 31),
(50, 'Mott', 31);

INSERT INTO `stuff_model` (`id`, `name`, `brand_id`) VALUES
-- RCF Aktív subwoofer modellek
(1, 'SUB 8003-AS II', 1),        -- $2,399
(2, 'SUB 9004-AS', 1),           -- $2,799
(3, 'SUB 905-AS II', 1),         -- $1,699

-- dbTechnologies Aktív subwoofer modellek
(4, 'SUB 918', 2),               -- $1,999
(5, 'SUB 915', 2),               -- $1,599
(6, 'VIO S118 R', 2),            -- $2,899

-- HK Audio Aktív subwoofer modellek
(7, 'Linear SUB 1500A', 3),      -- $999
(8, 'Linear SUB 2000A', 3),      -- $1,299
(9, 'PL 118 Sub A', 3),          -- $1,799

-- L-Acoustics Passzív subwoofer modellek
(10, 'SB28', 5),                 -- $6,999
(11, 'KS28', 5),                 -- $7,999
(12, 'SB18', 5),                 -- $5,999

-- D&B Audiotechnik Passzív subwoofer modellek
(13, 'B22-SUB', 6),              -- $4,999
(14, 'B4-SUB', 6),               -- $3,999
(15, 'V-GSUB', 6),               -- $5,499

-- Bose Aktív full-range modellek
(16, 'F1 Model 812', 8),         -- $1,199
(17, 'L1 Pro32', 8),             -- $2,199
(18, 'S1 Pro+', 8),              -- $699

-- QSC Aktív full-range modellek
(19, 'K12.2', 9),                -- $799
(20, 'K10.2', 9),                -- $699
(21, 'CP12', 9),                 -- $499

-- Allen&Heath Keverőpult modellek
(22, 'SQ-5', 21),                -- $2,999
(23, 'SQ-6', 21),                -- $3,699
(24, 'Avantis', 21),             -- $9,999

-- Behringer Keverőpult modellek
(25, 'X32', 22),                 -- $1,999
(26, 'Wing', 22),                -- $2,499
(27, 'X32 Compact', 22),         -- $1,499

-- Shure Mikrofon modellek
(28, 'SM58', 29),                -- $99
(29, 'SM7B', 29),                -- $399
(30, 'Beta 58A', 29),            -- $159

-- Sennheiser Mikrofon modellek
(31, 'e935', 30),                -- $169
(32, 'e945', 30),                -- $199
(33, 'MD 421-II', 30),           -- $379

-- Robe Spot lámpa modellek
(34, 'Pointe', 32),              -- $4,999
(35, 'MegaPointe', 32),          -- $7,999
(36, 'T1 Profile', 32),          -- $12,999

-- Clay Paky Wash lámpa modellek
(37, 'A.leda B-EYE K10', 36),    -- $5,999
(38, 'A.leda K20', 36),          -- $6,999
(39, 'HY B-EYE K25', 36),        -- $8,999

-- High End Systems Beam lámpa modellek
(40, 'SolaFrame 1000', 39),      -- $7,999
(41, 'SolaSpot 1500', 39),       -- $8,999
(42, 'SolaWash 2000', 39),       -- $9,999

-- ADJ LED Par modellek
(43, 'Mega QA Par38', 42),       -- $199
(44, '7P HEX IP', 42),           -- $299
(45, '12P HEX', 42);             -- $399

INSERT INTO `stuff_status` (`id`, `name`) VALUES
(1, 'Használatban'),
(2, 'Raktáron'), 
(3, 'Lefoglalva'), 
(4, 'Hibás'), 
(5, 'Karbantartónál'), 
(6, 'Törött'), 
(7, 'Kiszelektálás alatt');

INSERT INTO `maintenance_status` (`id`, `name`) VALUES
(1, 'Kész'),
(2, 'Javitás alatt'),
(3, 'Alkatrész beszerzés alatt'),
(4, 'Várakozik');

INSERT INTO `countries` (`id`, `name`) VALUES
(1, 'Magyarország'),
(2, 'Szlovákia');

INSERT INTO `counties` (`id`, `country_id`, `name`) VALUES
(1, 1, 'Bács-Kiskun'),
(2, 1, 'Baranya'),
(3, 1, 'Békés'),
(4, 1, 'Borsod-Abaúj-Zemplén'),
(5, 1, 'Budapest'),
(6, 1, 'Csongrád-Csanád'),
(7, 1, 'Fejér'),
(8, 1, 'Győr-Moson-Sopron'),
(9, 1, 'Hajdú-Bihar'),
(10, 1, 'Heves'),
(11, 1, 'Jász-Nagykun-Szolnok'),
(12, 1, 'Komárom-Esztergom'),
(13, 1, 'Nógrád'),
(14, 1, 'Pest'),
(15, 1, 'Somogy'),
(16, 1, 'Szabolcs-Szatmár-Bereg'),
(17, 1, 'Tolna'),
(18, 1, 'Vas'),
(19, 1, 'Veszprém'),
(20, 1, 'Zala'),
(21, 2, 'Pozsonyi kerület'),
(22, 2, 'Nagyszombati kerület'),
(23, 2, 'Nyitrai kerület'),
(24, 2, 'Trencséni kerület'),
(25, 2, 'Zsolnai kerület'),
(26, 2, 'Besztercebányai kerület'),
(27, 2, 'Eperjesi kerület'),
(28, 2, 'Kassai kerület');

INSERT INTO `districts` (`id`, `county_id`, `name`) VALUES
-- Budapest kerületei
(1, 5, 'I. kerület'),
(2, 5, 'II. kerület'),
(3, 5, 'III. kerület'),
(4, 5, 'IV. kerület'),
(5, 5, 'V. kerület'),
(6, 5, 'VI. kerület'),
(7, 5, 'VII. kerület'),
(8, 5, 'VIII. kerület'),
(9, 5, 'IX. kerület'),
(10, 5, 'X. kerület'),
(11, 5, 'XI. kerület'),
(12, 5, 'XII. kerület'),
(13, 5, 'XIII. kerület'),
(14, 5, 'XIV. kerület'),
(15, 5, 'XV. kerület'),
(16, 5, 'XVI. kerület'),
(17, 5, 'XVII. kerület'),
(18, 5, 'XVIII. kerület'),
(19, 5, 'XIX. kerület'),
(20, 5, 'XX. kerület'),
(21, 5, 'XXI. kerület'),
(22, 5, 'XXII. kerület'),
(23, 5, 'XXIII. kerület'),

-- Pozsonyi kerület járásai
(24, 21, 'Pozsonyi I. járás'),
(25, 21, 'Pozsonyi II. járás'),
(26, 21, 'Pozsonyi III. járás'),
(27, 21, 'Pozsonyi IV. járás'),
(28, 21, 'Pozsonyi V. járás'),
(29, 21, 'Szenci járás'),
(30, 21, 'Malackai járás'),
(31, 21, 'Bazini járás'),

-- Nagyszombati kerület járásai
(32, 22, 'Nagyszombati járás'),
(33, 22, 'Dunaszerdahelyi járás'),
(34, 22, 'Galántai járás'),
(35, 22, 'Vágsellyei járás'),

-- Nyitrai kerület járásai
(36, 23, 'Nyitrai járás'),
(37, 23, 'Érsekújvári járás'),
(38, 23, 'Komáromi járás'),
(39, 23, 'Lévai járás'),

-- Trencséni kerület járásai
(40, 24, 'Trencséni járás'),
(41, 24, 'Puhói járás'),
(42, 24, 'Vágbesztercei járás'),
(43, 24, 'Illavai járás'),

-- Zsolnai kerület járásai
(44, 25, 'Zsolnai járás'),
(45, 25, 'Csacai járás'),
(46, 25, 'Kiszucaújhelyi járás'),
(47, 25, 'Námesztói járás'),
(48, 25, 'Turdossini járás'),
(49, 25, 'Alsókubini járás'),
(50, 25, 'Liptószentmiklósi járás'),
(51, 25, 'Rózsahegyi járás'),
(52, 25, 'Martinyi járás'),

-- Besztercebányai kerület járásai
(53, 26, 'Besztercebányai járás'),
(54, 26, 'Breznóbányai járás'),
(55, 26, 'Gyetvai járás'),
(56, 26, 'Zólyomi járás'),
(57, 26, 'Losonci járás'),
(58, 26, 'Nagykürtösi járás'),
(59, 26, 'Poltári járás'),
(60, 26, 'Rimaszombati járás'),
(61, 26, 'Nagyrőcei járás'),
(62, 26, 'Zsarnócai járás'),
(63, 26, 'Garamszentkereszti járás'),
(64, 26, 'Selmecbányai járás'),

-- Eperjesi kerület járásai
(65, 27, 'Eperjesi járás'),
(66, 27, 'Bártfai járás'),
(67, 27, 'Homonnai járás'),
(68, 27, 'Késmárki járás'),
(69, 27, 'Lőcsei járás'),
(70, 27, 'Mezőlaborci járás'),
(71, 27, 'Poprádi járás'),
(72, 27, 'Kisszebeni járás'),
(73, 27, 'Sztropkói járás'),
(74, 27, 'Szvidniki járás'),
(75, 27, 'Varannói járás'),

-- Kassai kerület járásai
(76, 28, 'Kassai I. járás'),
(77, 28, 'Kassai II. járás'),
(78, 28, 'Kassai III. járás'),
(79, 28, 'Kassai IV. járás'),
(80, 28, 'Kassai-vidéki járás'),
(81, 28, 'Gölnicbányai járás'),
(82, 28, 'Iglói járás'),
(83, 28, 'Nagymihályi járás'),
(84, 28, 'Rozsnyói járás'),
(85, 28, 'Szobránci járás'),
(86, 28, 'Tőketerebesi járás');

INSERT INTO `cities` (`id`, `county_id`, `name`) VALUES
-- Budapest kerületei
(1, 5, 'Budapest I. kerület'),
(2, 5, 'Budapest II. kerület'),
(3, 5, 'Budapest III. kerület'),
(4, 5, 'Budapest IV. kerület'),
(5, 5, 'Budapest V. kerület'),
(6, 5, 'Budapest VI. kerület'),
(7, 5, 'Budapest VII. kerület'),
(8, 5, 'Budapest VIII. kerület'),
(9, 5, 'Budapest IX. kerület'),
(10, 5, 'Budapest X. kerület'),
(11, 5, 'Budapest XI. kerület'),
(12, 5, 'Budapest XII. kerület'),
(13, 5, 'Budapest XIII. kerület'),
(14, 5, 'Budapest XIV. kerület'),
(15, 5, 'Budapest XV. kerület'),
(16, 5, 'Budapest XVI. kerület'),
(17, 5, 'Budapest XVII. kerület'),
(18, 5, 'Budapest XVIII. kerület'),
(19, 5, 'Budapest XIX. kerület'),
(20, 5, 'Budapest XX. kerület'),
(21, 5, 'Budapest XXI. kerület'),
(22, 5, 'Budapest XXII. kerület'),
(23, 5, 'Budapest XXIII. kerület'),

-- Pest megye nagyobb városai
(24, 14, 'Érd'),

-- Bács-Kiskun megye
(25, 1, 'Kecskemét'),
(26, 1, 'Baja'),
(27, 1, 'Kiskunfélegyháza'),

-- Baranya megye
(28, 2, 'Pécs'),
(29, 2, 'Mohács'),
(30, 2, 'Komló'),

-- Békés megye
(31, 3, 'Békéscsaba'),
(32, 3, 'Gyula'),
(33, 3, 'Orosháza'),

-- Borsod-Abaúj-Zemplén megye
(34, 4, 'Miskolc'),
(35, 4, 'Ózd'),
(36, 4, 'Kazincbarcika'),

-- Csongrád-Csanád megye
(37, 6, 'Szeged'),
(38, 6, 'Hódmezővásárhely'),
(39, 6, 'Szentes'),

-- Fejér megye
(40, 7, 'Székesfehérvár'),
(41, 7, 'Dunaújváros'),
(42, 7, 'Mór'),

-- Győr-Moson-Sopron megye
(43, 8, 'Győr'),
(44, 8, 'Sopron'),
(45, 8, 'Mosonmagyaróvár'),

-- Hajdú-Bihar megye
(46, 9, 'Debrecen'),
(47, 9, 'Hajdúszoboszló'),
(48, 9, 'Hajdúböszörmény'),

-- Heves megye
(49, 10, 'Eger'),
(50, 10, 'Gyöngyös'),
(51, 10, 'Hatvan'),

-- Pozsonyi kerület városai
(52, 21, 'Pozsony'),
(53, 21, 'Szenc'),
(54, 21, 'Malacka'),

-- Nagyszombati kerület városai
(55, 22, 'Nagyszombat'),
(56, 22, 'Dunaszerdahely'),
(57, 22, 'Galánta'),

-- Nyitrai kerület városai
(58, 23, 'Nyitra'),
(59, 23, 'Érsekújvár'),
(60, 23, 'Komárom'),

-- Trencséni kerület városai
(61, 24, 'Trencsén'),
(62, 24, 'Puhó'),
(63, 24, 'Vágbeszterce'),

-- Zsolnai kerület városai
(64, 25, 'Zsolna'),
(65, 25, 'Turócszentmárton'),
(66, 25, 'Rózsahegy'),

-- Besztercebányai kerület városai
(67, 26, 'Besztercebánya'),
(68, 26, 'Losonc'),
(69, 26, 'Rimaszombat'),

-- Eperjesi kerület városai
(70, 27, 'Eperjes'),
(71, 27, 'Bártfa'),
(72, 27, 'Késmárk'),

-- Kassai kerület városai
(73, 28, 'Kassa'),
(74, 28, 'Rozsnyó'),
(75, 28, 'Tőketerebes'),

-- Pozsonyi kerület további városai
(76, 21, 'Bazin'),
(77, 21, 'Stomfa'),
(78, 21, 'Modor'),

-- Nagyszombati kerület további városai
(79, 22, 'Somorja'),
(80, 22, 'Vágsellye'),
(81, 22, 'Diószeg'),

-- Nyitrai kerület további városai
(82, 23, 'Párkány'),
(83, 23, 'Léva'),
(84, 23, 'Vágsellye'),

-- Trencséni kerület további városai
(85, 24, 'Illava'),
(86, 24, 'Dubnica'),
(87, 24, 'Vágbeszterce'),

-- Zsolnai kerület további városai
(88, 25, 'Csaca'),
(89, 25, 'Námesztó'),
(90, 25, 'Turdossin'),

-- Besztercebányai kerület további városai
(91, 26, 'Zólyom'),
(92, 26, 'Breznóbánya'),
(93, 26, 'Nagykürtös'),

-- Eperjesi kerület további városai
(94, 27, 'Lőcse'),
(95, 27, 'Igló'),
(96, 27, 'Kisszeben'),

-- Kassai kerület további városai
(97, 28, 'Nagymihály'),
(98, 28, 'Szepsi'),
(99, 28, 'Királyhelmec'),

-- További Zsolnai kerületi települések
(100, 25, 'Rajecfürdő'),
(101, 25, 'Rajec'),
(102, 25, 'Várna'),
(103, 25, 'Kiszucaújhely'),
(104, 25, 'Turzófalva'),
(105, 25, 'Alsókubin'),
(106, 25, 'Liptószentmiklós'),
(107, 25, 'Trstená'),

-- További Besztercebányai kerületi települések
(108, 26, 'Korpona'),
(109, 26, 'Gyetva'),
(110, 26, 'Nagyrőce'),
(111, 26, 'Poltár'),
(112, 26, 'Fülek'),
(113, 26, 'Zsarnóca'),
(114, 26, 'Selmecbánya'),
(115, 26, 'Garamszentkereszt'),
(116, 26, 'Tornalja'),

-- További Eperjesi kerületi települések
(117, 27, 'Felsővízköz'),
(118, 27, 'Mezőlaborc'),
(119, 27, 'Homonna'),
(120, 27, 'Varannó'),
(121, 27, 'Sztropkó'),
(122, 27, 'Szvidnik'),
(123, 27, 'Poprád'),
(124, 27, 'Ólubló'),
(125, 27, 'Podolin'),

-- További Kassai kerületi települések
(126, 28, 'Gölnicbánya'),
(127, 28, 'Szepsi'),
(128, 28, 'Szobránc'),
(129, 28, 'Nagyida'),
(130, 28, 'Mecenzéf'),
(131, 28, 'Szina'),
(132, 28, 'Pálóc'),
(133, 28, 'Nagykapos'),
(134, 28, 'Dobsina');

-- Ellenőrizzük, hogy a has_districts oszlop helyesen van beállítva
ALTER TABLE countries 
ADD COLUMN has_districts TINYINT(1) DEFAULT 0;

-- Állítsuk be újra Szlovákiát
UPDATE countries 
SET has_districts = 1 
WHERE name = 'Szlovákia';

-- Magyarországnál nincs kerület
UPDATE countries 
SET has_districts = 0 
WHERE name = 'Magyarország';