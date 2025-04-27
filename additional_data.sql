-- TISZTA, VALÓSÁGHŰ, CSOMAGLOGIKÁNAK MEGFELELŐ TESZTADATOK --
-- (A régi beszúrásokat töröltem, minden újragenerálva a megbeszéltek szerint)

-- Szükséges lookup táblák rekordjai (hogy ne legyen foreign key hiba)
INSERT IGNORE INTO `status` (`id`, `name`) VALUES
(1, 'Elérhető'),
(2, 'Munkában'),
(3, 'Lefoglalt'),
(4, 'Szabadság'),
(5, 'Betegállomány');

INSERT IGNORE INTO `roles` (`id`, `role_name`) VALUES
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

INSERT IGNORE INTO `subscription_statuses` (`id`, `name`) VALUES
(1, 'aktív'),
(2, 'lemondott'),
(3, 'lejárt'),
(4, 'függőben');

INSERT IGNORE INTO `payment_statuses` (`id`, `name`) VALUES
(1, 'sikeres'),
(2, 'sikertelen'),
(3, 'függőben'),
(4, 'visszatérített');

-- 1. Cégek beszúrása (vegyes ország, minden csomag)
INSERT INTO `company` (`company_name`, `company_address`, `company_email`, `company_telephone`, `created_date`) VALUES
('EventPro Solutions', 'Budapest, Példa utca 123.', 'info@eventpro.hu', '06301234568', '2025-01-25 10:00:00'),
('SlovakStage s.r.o.', 'Bratislava, Hlavná 12.', 'info@slovakstage.sk', '421901234567', '2025-05-10 09:00:00'),
('StageTech Hungary', 'Debrecen, Minta utca 45.', 'info@stagetch.hu', '06301234569', '2025-01-28 14:30:00'),
('EventVision CZ', 'Praha, Náměstí 8.', 'info@eventvision.cz', '420777888999', '2025-05-20 11:00:00'),
('SoundWave Events', 'Szeged, Rendezvény tér 7.', 'info@soundwave.hu', '06301234570', '2025-02-01 09:15:00'),
('BühneProfi GmbH', 'Wien, Musterstraße 5.', 'info@buehneprofi.at', '4311234567', '2025-04-15 10:00:00'),
('RomanianEvents SRL', 'Cluj, Strada Exemplu 3.', 'info@romevents.ro', '40712345678', '2025-05-25 12:00:00'),
('StageLight Solutions', 'Kaposvár, Fény utca 89.', 'info@stagelight.hu', '06301234580', '2025-04-01 13:25:00'),
('SerbiaEvents', 'Beograd, Primer 9.', 'info@serbiaevents.rs', '381601234567', '2025-05-30 14:00:00'),
('TestTrial Kft.', 'Budapest, Próba utca 1.', 'info@testtrial.hu', '06309999999', '2025-06-01 08:00:00');

-- 2. Felhasználók beszúrása (minden céghez a csomaghoz illő számú user, legalább 1 tulajdonos, többi munkás)
INSERT INTO `user` (`firstname`, `lastname`, `email`, `telephone`, `password`, `company_id`, `current_status_id`, `created_date`, `is_email_verified`) VALUES
-- EventPro Solutions (7 user)
('János', 'Kovács', 'kovacs@eventpro.hu', '06301234583', '$2y$10$Teszt1234', 1, 1, '2025-01-25 10:00:00', 1),
('László', 'Kiss', 'kiss.l@eventpro.hu', '06301234598', '$2y$10$Teszt1234', 1, 1, '2025-01-26 10:00:00', 1),
('Márta', 'Nagy', 'nagy.m@eventpro.hu', '06301234599', '$2y$10$Teszt1234', 1, 1, '2025-01-27 10:00:00', 1),
('József', 'Szabó', 'szabo.j@eventpro.hu', '06301234600', '$2y$10$Teszt1234', 1, 1, '2025-01-28 10:00:00', 1),
('Erzsébet', 'Tóth', 'toth.e@eventpro.hu', '06301234601', '$2y$10$Teszt1234', 1, 1, '2025-01-29 10:00:00', 1),
('Tibor', 'Fekete', 'fekete.tibor@eventpro.hu', '06301234610', '$2y$10$Teszt1234', 1, 1, '2025-01-30 10:00:00', 1),
('Zoltán', 'Fehér', 'feher.zoltan@eventpro.hu', '06301234611', '$2y$10$Teszt1234', 1, 1, '2025-01-31 10:00:00', 1),

-- SlovakStage s.r.o. (15 user - üzleti csomag)
('Marek', 'Novak', 'novak@slovakstage.sk', '421901234568', '$2y$10$Teszt1234', 2, 1, '2025-05-10 09:00:00', 1),
('Jozef', 'Kral', 'kral@slovakstage.sk', '421901234569', '$2y$10$Teszt1234', 2, 1, '2025-05-11 09:00:00', 1),
('Lukas', 'Urban', 'urban@slovakstage.sk', '421901234570', '$2y$10$Teszt1234', 2, 1, '2025-05-12 09:00:00', 1),
('Peter', 'Kovac', 'kovac@slovakstage.sk', '421901234571', '$2y$10$Teszt1234', 2, 1, '2025-05-13 09:00:00', 1),
('Milan', 'Horvath', 'horvath@slovakstage.sk', '421901234572', '$2y$10$Teszt1234', 2, 1, '2025-05-14 09:00:00', 1),
('Eva', 'Szabova', 'szabova@slovakstage.sk', '421901234573', '$2y$10$Teszt1234', 2, 1, '2025-05-15 09:00:00', 1),
('Tomas', 'Varga', 'varga@slovakstage.sk', '421901234574', '$2y$10$Teszt1234', 2, 1, '2025-05-16 09:00:00', 1),
('Jana', 'Balazs', 'balazs@slovakstage.sk', '421901234575', '$2y$10$Teszt1234', 2, 1, '2025-05-17 09:00:00', 1),
('Martin', 'Toth', 'toth@slovakstage.sk', '421901234576', '$2y$10$Teszt1234', 2, 1, '2025-05-18 09:00:00', 1),
('Zuzana', 'Nagy', 'nagy@slovakstage.sk', '421901234577', '$2y$10$Teszt1234', 2, 1, '2025-05-19 09:00:00', 1),
('Richard', 'Molnar', 'molnar@slovakstage.sk', '421901234578', '$2y$10$Teszt1234', 2, 1, '2025-05-20 09:00:00', 1),
('Andrea', 'Kovacs', 'kovacs@slovakstage.sk', '421901234579', '$2y$10$Teszt1234', 2, 1, '2025-05-21 09:00:00', 1),
('Daniel', 'Lakatos', 'lakatos@slovakstage.sk', '421901234580', '$2y$10$Teszt1234', 2, 1, '2025-05-22 09:00:00', 1),
('Katarina', 'Simon', 'simon@slovakstage.sk', '421901234581', '$2y$10$Teszt1234', 2, 1, '2025-05-23 09:00:00', 1),
('Michal', 'Farkas', 'farkas@slovakstage.sk', '421901234582', '$2y$10$Teszt1234', 2, 1, '2025-05-24 09:00:00', 1),

-- BühneProfi GmbH (15 user - üzleti csomag)
('Anna', 'Müller', 'mueller@buehneprofi.at', '4311234568', '$2y$10$Teszt1234', 6, 1, '2025-04-15 10:00:00', 1),
('Sophie', 'Schmidt', 'schmidt@buehneprofi.at', '4311234569', '$2y$10$Teszt1234', 6, 1, '2025-04-16 10:00:00', 1),
('Thomas', 'Wagner', 'wagner@buehneprofi.at', '4311234570', '$2y$10$Teszt1234', 6, 1, '2025-04-17 10:00:00', 1),
('Michael', 'Bauer', 'bauer@buehneprofi.at', '4311234571', '$2y$10$Teszt1234', 6, 1, '2025-04-18 10:00:00', 1),
('Lisa', 'Hoffmann', 'hoffmann@buehneprofi.at', '4311234572', '$2y$10$Teszt1234', 6, 1, '2025-04-19 10:00:00', 1),
('David', 'Koch', 'koch@buehneprofi.at', '4311234573', '$2y$10$Teszt1234', 6, 1, '2025-04-20 10:00:00', 1),
('Julia', 'Weber', 'weber@buehneprofi.at', '4311234574', '$2y$10$Teszt1234', 6, 1, '2025-04-21 10:00:00', 1),
('Markus', 'Meyer', 'meyer@buehneprofi.at', '4311234575', '$2y$10$Teszt1234', 6, 1, '2025-04-22 10:00:00', 1),
('Sarah', 'Fischer', 'fischer@buehneprofi.at', '4311234576', '$2y$10$Teszt1234', 6, 1, '2025-04-23 10:00:00', 1),
('Andreas', 'Huber', 'huber@buehneprofi.at', '4311234577', '$2y$10$Teszt1234', 6, 1, '2025-04-24 10:00:00', 1),
('Christina', 'Berger', 'berger@buehneprofi.at', '4311234578', '$2y$10$Teszt1234', 6, 1, '2025-04-25 10:00:00', 1),
('Stefan', 'Gruber', 'gruber@buehneprofi.at', '4311234579', '$2y$10$Teszt1234', 6, 1, '2025-04-26 10:00:00', 1),
('Laura', 'Wolf', 'wolf@buehneprofi.at', '4311234580', '$2y$10$Teszt1234', 6, 1, '2025-04-27 10:00:00', 1),
('Felix', 'Schwarz', 'schwarz@buehneprofi.at', '4311234581', '$2y$10$Teszt1234', 6, 1, '2025-04-28 10:00:00', 1),
('Hannah', 'Steiner', 'steiner@buehneprofi.at', '4311234582', '$2y$10$Teszt1234', 6, 1, '2025-04-29 10:00:00', 1),

-- StageTech Hungary (5 user - alap csomag)
('Péter', 'Nagy', 'nagy@stagetch.hu', '06301234584', '$2y$10$Teszt1234', 3, 1, '2025-01-28 14:30:00', 1),
('Gábor', 'Kiss', 'kiss@stagetch.hu', '06301234590', '$2y$10$Teszt1234', 3, 1, '2025-01-29 14:30:00', 1),
('István', 'Kovács', 'kovacs@stagetch.hu', '06301234591', '$2y$10$Teszt1234', 3, 1, '2025-01-30 14:30:00', 1),
('Ferenc', 'Szabó', 'szabo@stagetch.hu', '06301234592', '$2y$10$Teszt1234', 3, 1, '2025-01-31 14:30:00', 1),
('Zsolt', 'Tóth', 'toth@stagetch.hu', '06301234593', '$2y$10$Teszt1234', 3, 1, '2025-02-01 14:30:00', 1),

-- SoundWave Events (5 user - alap csomag)
('Anna', 'Kiss', 'kiss@soundwave.hu', '06301234585', '$2y$10$Teszt1234', 5, 1, '2025-02-01 09:15:00', 1),
('Balázs', 'Nagy', 'nagy@soundwave.hu', '06301234586', '$2y$10$Teszt1234', 5, 1, '2025-02-02 09:15:00', 1),
('Csaba', 'Kovács', 'kovacs@soundwave.hu', '06301234587', '$2y$10$Teszt1234', 5, 1, '2025-02-03 09:15:00', 1),
('Dóra', 'Szabó', 'szabo@soundwave.hu', '06301234588', '$2y$10$Teszt1234', 5, 1, '2025-02-04 09:15:00', 1),
('Eszter', 'Tóth', 'toth@soundwave.hu', '06301234589', '$2y$10$Teszt1234', 5, 1, '2025-02-05 09:15:00', 1),

-- StageLight Solutions (8 user - közepes csomag)
('Judit', 'Kiss', 'kiss@stagelight.hu', '06301234595', '$2y$10$Teszt1234', 8, 1, '2025-04-01 13:25:00', 1),
('Tamás', 'Szabó', 'szabo@stagelight.hu', '06301234596', '$2y$10$Teszt1234', 8, 1, '2025-04-02 13:25:00', 1),
('Péter', 'Nagy', 'nagy@stagelight.hu', '06301234597', '$2y$10$Teszt1234', 8, 1, '2025-04-03 13:25:00', 1),
('Katalin', 'Kovács', 'kovacs@stagelight.hu', '06301234598', '$2y$10$Teszt1234', 8, 1, '2025-04-04 13:25:00', 1),
('András', 'Tóth', 'toth@stagelight.hu', '06301234599', '$2y$10$Teszt1234', 8, 1, '2025-04-05 13:25:00', 1),
('Éva', 'Horváth', 'horvath@stagelight.hu', '06301234600', '$2y$10$Teszt1234', 8, 1, '2025-04-06 13:25:00', 1),
('Zoltán', 'Fekete', 'fekete@stagelight.hu', '06301234601', '$2y$10$Teszt1234', 8, 1, '2025-04-07 13:25:00', 1),
('Mária', 'Varga', 'varga@stagelight.hu', '06301234602', '$2y$10$Teszt1234', 8, 1, '2025-04-08 13:25:00', 1),

-- TestTrial Kft. (2 user - free trial)
('Test', 'Trial', 'testtrial@testtrial.hu', '06309999998', '$2y$10$Teszt1234', 10, 1, '2025-06-01 08:00:00', 1),
('Test2', 'Trial2', 'testtrial2@testtrial.hu', '06309999997', '$2y$10$Teszt1234', 10, 1, '2025-06-02 08:00:00', 1);

-- 3. Role kiosztás (user_to_roles)
INSERT INTO `user_to_roles` (`user_id`, `role_id`) VALUES
-- EventPro Solutions roles
((SELECT id FROM user WHERE email = 'kovacs@eventpro.hu'), 1),
((SELECT id FROM user WHERE email = 'kovacs@eventpro.hu'), 8),
((SELECT id FROM user WHERE email = 'kiss.l@eventpro.hu'), 2),
((SELECT id FROM user WHERE email = 'nagy.m@eventpro.hu'), 3),
((SELECT id FROM user WHERE email = 'szabo.j@eventpro.hu'), 4),
((SELECT id FROM user WHERE email = 'toth.e@eventpro.hu'), 5),
((SELECT id FROM user WHERE email = 'fekete.tibor@eventpro.hu'), 6),
((SELECT id FROM user WHERE email = 'feher.zoltan@eventpro.hu'), 7),

-- SlovakStage roles
((SELECT id FROM user WHERE email = 'novak@slovakstage.sk'), 1),
((SELECT id FROM user WHERE email = 'novak@slovakstage.sk'), 8),
((SELECT id FROM user WHERE email = 'kral@slovakstage.sk'), 2),
((SELECT id FROM user WHERE email = 'urban@slovakstage.sk'), 3),
((SELECT id FROM user WHERE email = 'kovac@slovakstage.sk'), 4),
((SELECT id FROM user WHERE email = 'horvath@slovakstage.sk'), 5),
((SELECT id FROM user WHERE email = 'szabova@slovakstage.sk'), 6),
((SELECT id FROM user WHERE email = 'varga@slovakstage.sk'), 7),
((SELECT id FROM user WHERE email = 'balazs@slovakstage.sk'), 9),
((SELECT id FROM user WHERE email = 'toth@slovakstage.sk'), 10),
((SELECT id FROM user WHERE email = 'nagy@slovakstage.sk'), 2),
((SELECT id FROM user WHERE email = 'molnar@slovakstage.sk'), 3),
((SELECT id FROM user WHERE email = 'kovacs@slovakstage.sk'), 4),
((SELECT id FROM user WHERE email = 'lakatos@slovakstage.sk'), 5),
((SELECT id FROM user WHERE email = 'simon@slovakstage.sk'), 6),
((SELECT id FROM user WHERE email = 'farkas@slovakstage.sk'), 7),

-- BühneProfi roles
((SELECT id FROM user WHERE email = 'mueller@buehneprofi.at'), 1),
((SELECT id FROM user WHERE email = 'schmidt@buehneprofi.at'), 2),
((SELECT id FROM user WHERE email = 'wagner@buehneprofi.at'), 3),
((SELECT id FROM user WHERE email = 'bauer@buehneprofi.at'), 4),
((SELECT id FROM user WHERE email = 'hoffmann@buehneprofi.at'), 5),
((SELECT id FROM user WHERE email = 'koch@buehneprofi.at'), 6),
((SELECT id FROM user WHERE email = 'weber@buehneprofi.at'), 7),
((SELECT id FROM user WHERE email = 'meyer@buehneprofi.at'), 8),
((SELECT id FROM user WHERE email = 'fischer@buehneprofi.at'), 9),
((SELECT id FROM user WHERE email = 'huber@buehneprofi.at'), 10),
((SELECT id FROM user WHERE email = 'berger@buehneprofi.at'), 2),
((SELECT id FROM user WHERE email = 'gruber@buehneprofi.at'), 3),
((SELECT id FROM user WHERE email = 'wolf@buehneprofi.at'), 4),
((SELECT id FROM user WHERE email = 'schwarz@buehneprofi.at'), 5),
((SELECT id FROM user WHERE email = 'steiner@buehneprofi.at'), 6),

-- StageTech Hungary roles
((SELECT id FROM user WHERE email = 'nagy@stagetch.hu'), 1),
((SELECT id FROM user WHERE email = 'kiss@stagetch.hu'), 2),
((SELECT id FROM user WHERE email = 'kovacs@stagetch.hu'), 3),
((SELECT id FROM user WHERE email = 'szabo@stagetch.hu'), 4),
((SELECT id FROM user WHERE email = 'toth@stagetch.hu'), 5),

-- SoundWave Events roles
((SELECT id FROM user WHERE email = 'kiss@soundwave.hu'), 1),
((SELECT id FROM user WHERE email = 'nagy@soundwave.hu'), 2),
((SELECT id FROM user WHERE email = 'kovacs@soundwave.hu'), 3),
((SELECT id FROM user WHERE email = 'szabo@soundwave.hu'), 4),
((SELECT id FROM user WHERE email = 'toth@soundwave.hu'), 5),

-- StageLight Solutions roles
((SELECT id FROM user WHERE email = 'kiss@stagelight.hu'), 1),
((SELECT id FROM user WHERE email = 'szabo@stagelight.hu'), 2),
((SELECT id FROM user WHERE email = 'nagy@stagelight.hu'), 3),
((SELECT id FROM user WHERE email = 'kovacs@stagelight.hu'), 4),
((SELECT id FROM user WHERE email = 'toth@stagelight.hu'), 5),
((SELECT id FROM user WHERE email = 'horvath@stagelight.hu'), 6),
((SELECT id FROM user WHERE email = 'fekete@stagelight.hu'), 7),
((SELECT id FROM user WHERE email = 'varga@stagelight.hu'), 8),

-- TestTrial roles
((SELECT id FROM user WHERE email = 'testtrial@testtrial.hu'), 1),
((SELECT id FROM user WHERE email = 'testtrial2@testtrial.hu'), 2);

-- 4. Payment methods (minden tulajdonosnak, és néhány random usernek is)
INSERT INTO `payment_methods` (`user_id`, `card_holder_name`, `CVC`, `card_expiry_month`, `card_expiry_year`, `is_default`, `card_type`, `last_four_digits`) VALUES
((SELECT id FROM user WHERE email = 'kovacs@eventpro.hu'), 'János Kovács', '123', '12', '2025', 1, 'Visa', '1234'),
((SELECT id FROM user WHERE email = 'novak@slovakstage.sk'), 'Marek Novak', '111', '12', '2026', 1, 'Visa', '1111'),
((SELECT id FROM user WHERE email = 'nagy@stagetch.hu'), 'Péter Nagy', '234', '12', '2025', 1, 'Mastercard', '2345'),
((SELECT id FROM user WHERE email = 'mueller@buehneprofi.at'), 'Anna Müller', '222', '11', '2027', 1, 'Mastercard', '2222'),
((SELECT id FROM user WHERE email = 'kiss@soundwave.hu'), 'Anna Kiss', '345', '12', '2025', 1, 'Visa', '3456'),
((SELECT id FROM user WHERE email = 'kiss@stagelight.hu'), 'Judit Kiss', '345', '12', '2025', 1, 'Visa', '3456'),
((SELECT id FROM user WHERE email = 'testtrial@testtrial.hu'), 'Test Trial', '999', '06', '2025', 1, 'Visa', '9999');

-- 5. Előfizetések (subscriptions) - minden cégnek 1 aktív, a csomaghoz/módosításhoz illő
INSERT INTO `subscriptions` (`user_id`, `company_id`, `subscription_plan_id`, `payment_method_id`, `subscription_status_id`, `start_date`, `end_date`, `next_billing_date`, `auto_renewal`, `trial_end_date`, `cancelled_at`) VALUES
((SELECT id FROM user WHERE email = 'kovacs@eventpro.hu'), 1, 2, (SELECT id FROM payment_methods WHERE user_id = (SELECT id FROM user WHERE email = 'kovacs@eventpro.hu')), 1, '2025-01-25 10:00:00', '2025-02-25 10:00:00', '2025-02-25 10:00:00', 1, NULL, NULL),
((SELECT id FROM user WHERE email = 'novak@slovakstage.sk'), 2, 7, (SELECT id FROM payment_methods WHERE user_id = (SELECT id FROM user WHERE email = 'novak@slovakstage.sk')), 1, '2025-05-10 09:00:00', '2025-06-10 09:00:00', '2025-06-10 09:00:00', 1, NULL, NULL),
((SELECT id FROM user WHERE email = 'nagy@stagetch.hu'), 3, 4, (SELECT id FROM payment_methods WHERE user_id = (SELECT id FROM user WHERE email = 'nagy@stagetch.hu')), 1, '2025-01-28 14:30:00', '2026-01-28 14:30:00', '2026-01-28 14:30:00', 1, NULL, NULL),
((SELECT id FROM user WHERE email = 'mueller@buehneprofi.at'), 6, 8, (SELECT id FROM payment_methods WHERE user_id = (SELECT id FROM user WHERE email = 'mueller@buehneprofi.at')), 1, '2025-04-15 10:00:00', '2026-04-15 10:00:00', '2026-04-15 10:00:00', 1, NULL, NULL),
((SELECT id FROM user WHERE email = 'kiss@soundwave.hu'), 5, 1, (SELECT id FROM payment_methods WHERE user_id = (SELECT id FROM user WHERE email = 'kiss@soundwave.hu')), 1, '2025-02-01 09:15:00', '2025-03-01 09:15:00', '2025-03-01 09:15:00', 1, NULL, NULL),
((SELECT id FROM user WHERE email = 'kiss@stagelight.hu'), 8, 2, (SELECT id FROM payment_methods WHERE user_id = (SELECT id FROM user WHERE email = 'kiss@stagelight.hu')), 1, '2025-04-01 13:25:00', '2025-05-01 13:25:00', '2025-05-01 13:25:00', 1, NULL, NULL),
((SELECT id FROM user WHERE email = 'testtrial@testtrial.hu'), 10, 9, (SELECT id FROM payment_methods WHERE user_id = (SELECT id FROM user WHERE email = 'testtrial@testtrial.hu')), 1, '2025-06-01 08:00:00', '2025-06-15 08:00:00', '2025-06-15 08:00:00', 1, '2025-06-15 08:00:00', NULL);

-- 6. Csomagváltás, módosítások (subscription_modifications) - többféle, reális
INSERT INTO `subscription_modifications` (`subscription_id`, `original_plan_id`, `modified_plan_id`, `modification_date`, `modified_by_user_id`, `price_difference`, `modification_reason`) VALUES
((SELECT id FROM subscriptions WHERE company_id = 2), 2, 7, '2025-05-15 09:00:00', (SELECT id FROM user WHERE email = 'novak@slovakstage.sk'), 25000.00, '10 → 20 felhasználó, 250 → 500 eszköz'),
((SELECT id FROM subscriptions WHERE company_id = 3), 4, 5, '2025-02-01 10:00:00', (SELECT id FROM user WHERE email = 'nagy@stagetch.hu'), 26000.00, '5 → 10 felhasználó, 100 → 250 eszköz'),
((SELECT id FROM subscriptions WHERE company_id = 8), 2, 2, '2025-04-10 13:25:00', (SELECT id FROM user WHERE email = 'kiss@stagelight.hu'), 5000.00, '10 → 12 felhasználó, 250 → 300 eszköz'),
((SELECT id FROM subscriptions WHERE company_id = 10), 9, 9, '2025-06-10 08:00:00', (SELECT id FROM user WHERE email = 'testtrial@testtrial.hu'), 0.00, '2 → 2 felhasználó, 10 → 10 eszköz');

-- 7. Státuszok, history (status_history) - aktív, lemondott, lejárt, függőben, reális időpontokkal
INSERT INTO `status_history` (`user_id`, `status_id`, `status_startdate`, `status_enddate`) VALUES
((SELECT id FROM user WHERE email = 'kovacs@eventpro.hu'), 1, '2025-01-25 10:00:00', '2025-02-25 10:00:00'),
((SELECT id FROM user WHERE email = 'novak@slovakstage.sk'), 1, '2025-05-10 09:00:00', '2025-06-10 09:00:00'),
((SELECT id FROM user WHERE email = 'testtrial@testtrial.hu'), 1, '2025-06-01 08:00:00', '2025-06-15 08:00:00'),
((SELECT id FROM user WHERE email = 'testtrial@testtrial.hu'), 4, '2025-06-15 08:00:00', NULL);

-- Szükséges manufacture_date rekordok (év, model_id, id)
INSERT IGNORE INTO `stuff_manufacture_date` (`id`, `year`, `stuff_model_id`) VALUES
(1, 2020, 1),
(2, 2021, 2),
(3, 2022, 34),
(4, 2023, 37),
(5, 2024, 43);

-- 8. Eszközök (stuffs) - csomaghoz/módosításhoz illő szám (csak minta, valóságban többet generálj)
INSERT INTO `stuffs` (`type_id`, `secondtype_id`, `brand_id`, `model_id`, `manufacture_date`, `favourite`, `stuff_status_id`, `qr_code`, `company_id`) VALUES
(1, 1, 1, 1, 1, 0, 1, 'QR001', 1),
(1, 2, 2, 2, 2, 0, 1, 'QR002', 1),
(2, 15, 32, 34, 3, 0, 1, 'QR003', 2),
(2, 16, 36, 37, 4, 0, 1, 'QR004', 2),
(3, 28, 42, 43, 5, 0, 1, 'QR005', 3);

-- 9. Fizetési előzmények (payment_history) - minden előfizetéshez
INSERT INTO `payment_history` (`subscription_id`, `payment_method_id`, `amount`, `payment_status_id`, `transaction_id`, `payment_date`, `payment_method_type`, `transaction_status`) VALUES
((SELECT id FROM subscriptions WHERE company_id = 1), (SELECT id FROM payment_methods WHERE user_id = (SELECT id FROM user WHERE email = 'kovacs@eventpro.hu')), 29990.00, 1, 'TRANS001', '2025-01-25 10:00:00', 'credit_card', 'completed'),
((SELECT id FROM subscriptions WHERE company_id = 2), (SELECT id FROM payment_methods WHERE user_id = (SELECT id FROM user WHERE email = 'novak@slovakstage.sk')), 80990.00, 1, 'TRANS002', '2025-05-10 09:00:00', 'credit_card', 'completed'),
((SELECT id FROM subscriptions WHERE company_id = 3), (SELECT id FROM payment_methods WHERE user_id = (SELECT id FROM user WHERE email = 'nagy@stagetch.hu')), 299990.00, 1, 'TRANS003', '2025-01-28 14:30:00', 'credit_card', 'completed'),
((SELECT id FROM subscriptions WHERE company_id = 6), (SELECT id FROM payment_methods WHERE user_id = (SELECT id FROM user WHERE email = 'mueller@buehneprofi.at')), 826098.00, 1, 'TRANS004', '2025-04-15 10:00:00', 'credit_card', 'completed'),
((SELECT id FROM subscriptions WHERE company_id = 10), (SELECT id FROM payment_methods WHERE user_id = (SELECT id FROM user WHERE email = 'testtrial@testtrial.hu')), 0.00, 1, 'TRANS005', '2025-06-01 08:00:00', 'credit_card', 'completed');

-- 10. Néhány belépési email-jelszó páros (teszteléshez)
-- Email: kovacs@eventpro.hu | Jelszó: Teszt1234
-- Email: novak@slovakstage.sk | Jelszó: Teszt1234
-- Email: nagy@stagetch.hu | Jelszó: Teszt1234
-- Email: mueller@buehneprofi.at | Jelszó: Teszt1234
-- Email: testtrial@testtrial.hu | Jelszó: Teszt1234

-- Admin user beszúrása
INSERT INTO `user` (`firstname`, `lastname`, `email`, `telephone`, `password`, `current_status_id`, `created_date`, `is_email_verified`) VALUES
('Misi', 'Admin', 'heszmilan@gmail.com', '06301234999', '$2y$10$asdasd123', 1, '2025-01-01 00:00:00', 1);

-- Admin role kiosztás
INSERT INTO `user_to_roles` (`user_id`, `role_id`) VALUES
((SELECT id FROM user WHERE email = 'heszmilan@gmail.com'), 1);

-- Hiányzó előfizetések hozzáadása
INSERT INTO `payment_methods` (`user_id`, `card_holder_name`, `CVC`, `card_expiry_month`, `card_expiry_year`, `is_default`, `card_type`, `last_four_digits`) VALUES
((SELECT id FROM user WHERE email = 'toth@eventstage.hu'), 'Krisztina Tóth', '777', '12', '2025', 1, 'Visa', '7777');

-- EventVision CZ előfizetés (lemondott)
INSERT INTO `subscriptions` (`user_id`, `company_id`, `subscription_plan_id`, `payment_method_id`, `subscription_status_id`, `start_date`, `end_date`, `next_billing_date`, `auto_renewal`, `trial_end_date`, `cancelled_at`) VALUES
((SELECT id FROM user WHERE email = 'toth@eventstage.hu'), 4, 5, (SELECT id FROM payment_methods WHERE user_id = (SELECT id FROM user WHERE email = 'toth@eventstage.hu')), 2, '2025-05-20 11:00:00', '2025-06-20 11:00:00', NULL, 0, NULL, '2025-06-01 11:00:00');

-- RomanianEvents SRL előfizetés (lejárt)
INSERT INTO `subscriptions` (`user_id`, `company_id`, `subscription_plan_id`, `payment_method_id`, `subscription_status_id`, `start_date`, `end_date`, `next_billing_date`, `auto_renewal`, `trial_end_date`, `cancelled_at`) VALUES
((SELECT id FROM user WHERE email = 'toth@eventstage.hu'), 7, 7, (SELECT id FROM payment_methods WHERE user_id = (SELECT id FROM user WHERE email = 'toth@eventstage.hu')), 3, '2025-05-25 12:00:00', '2025-06-25 12:00:00', NULL, 0, NULL, NULL);

-- SerbiaEvents előfizetés (függőben)
INSERT INTO `subscriptions` (`user_id`, `company_id`, `subscription_plan_id`, `payment_method_id`, `subscription_status_id`, `start_date`, `end_date`, `next_billing_date`, `auto_renewal`, `trial_end_date`, `cancelled_at`) VALUES
((SELECT id FROM user WHERE email = 'toth@eventstage.hu'), 9, 9, (SELECT id FROM payment_methods WHERE user_id = (SELECT id FROM user WHERE email = 'toth@eventstage.hu')), 4, '2025-05-30 14:00:00', NULL, NULL, 1, '2025-06-13 14:00:00', NULL);

-- Subscription módosítások
INSERT INTO `subscription_modifications` (`subscription_id`, `original_plan_id`, `modified_plan_id`, `modification_date`, `modified_by_user_id`, `price_difference`, `modification_reason`) VALUES
((SELECT id FROM subscriptions WHERE company_id = 4), 1, 5, '2025-05-25 11:00:00', (SELECT id FROM user WHERE email = 'toth@eventstage.hu'), 260000.00, '5 → 10 felhasználó, éves csomagra váltás'),
((SELECT id FROM subscriptions WHERE company_id = 7), 2, 7, '2025-06-01 12:00:00', (SELECT id FROM user WHERE email = 'toth@eventstage.hu'), 25000.00, '10 → 20 felhasználó bővítés');

-- Payment history az új előfizetésekhez
INSERT INTO `payment_history` (`subscription_id`, `payment_method_id`, `amount`, `payment_status_id`, `transaction_id`, `payment_date`, `payment_method_type`, `transaction_status`) VALUES
((SELECT id FROM subscriptions WHERE company_id = 4), (SELECT id FROM payment_methods WHERE user_id = (SELECT id FROM user WHERE email = 'toth@eventstage.hu')), 559990.00, 1, 'TRANS006', '2025-05-20 11:00:00', 'credit_card', 'completed'),
((SELECT id FROM subscriptions WHERE company_id = 7), (SELECT id FROM payment_methods WHERE user_id = (SELECT id FROM user WHERE email = 'toth@eventstage.hu')), 80990.00, 1, 'TRANS007', '2025-05-25 12:00:00', 'credit_card', 'completed'),
((SELECT id FROM subscriptions WHERE company_id = 9), (SELECT id FROM payment_methods WHERE user_id = (SELECT id FROM user WHERE email = 'toth@eventstage.hu')), 0.00, 3, 'TRANS008', '2025-05-30 14:00:00', 'credit_card', 'pending');

-- EventVision CZ (közepes csomag - 10 felhasználó)
INSERT INTO `user` (`firstname`, `lastname`, `email`, `telephone`, `password`, `company_id`, `current_status_id`, `created_date`, `is_email_verified`) VALUES
('Pavel', 'Novotny', 'novotny@eventvision.cz', '420777888991', '$2y$10$Teszt1234', 4, 1, '2025-05-20 11:00:00', 1),
('Jan', 'Dvorak', 'dvorak@eventvision.cz', '420777888992', '$2y$10$Teszt1234', 4, 1, '2025-05-21 11:00:00', 1),
('Martin', 'Svoboda', 'svoboda@eventvision.cz', '420777888993', '$2y$10$Teszt1234', 4, 1, '2025-05-22 11:00:00', 1),
('Tomas', 'Kral', 'kral@eventvision.cz', '420777888994', '$2y$10$Teszt1234', 4, 1, '2025-05-23 11:00:00', 1),
('Jakub', 'Marek', 'marek@eventvision.cz', '420777888995', '$2y$10$Teszt1234', 4, 1, '2025-05-24 11:00:00', 1),
('David', 'Polak', 'polak@eventvision.cz', '420777888996', '$2y$10$Teszt1234', 4, 1, '2025-05-25 11:00:00', 1),
('Petr', 'Vesely', 'vesely@eventvision.cz', '420777888997', '$2y$10$Teszt1234', 4, 1, '2025-05-26 11:00:00', 1),
('Josef', 'Horak', 'horak@eventvision.cz', '420777888998', '$2y$10$Teszt1234', 4, 1, '2025-05-27 11:00:00', 1);

-- RomanianEvents SRL (üzleti csomag - 20 felhasználó)
INSERT INTO `user` (`firstname`, `lastname`, `email`, `telephone`, `password`, `company_id`, `current_status_id`, `created_date`, `is_email_verified`) VALUES
('Ion', 'Popescu', 'popescu@romevents.ro', '40712345679', '$2y$10$Teszt1234', 7, 1, '2025-05-25 12:00:00', 1),
('Maria', 'Ionescu', 'ionescu@romevents.ro', '40712345680', '$2y$10$Teszt1234', 7, 1, '2025-05-26 12:00:00', 1),
('Andrei', 'Popa', 'popa@romevents.ro', '40712345681', '$2y$10$Teszt1234', 7, 1, '2025-05-27 12:00:00', 1),
('Elena', 'Stan', 'stan@romevents.ro', '40712345682', '$2y$10$Teszt1234', 7, 1, '2025-05-28 12:00:00', 1),
('Adrian', 'Radu', 'radu@romevents.ro', '40712345683', '$2y$10$Teszt1234', 7, 1, '2025-05-29 12:00:00', 1),
('Cristina', 'Dumitru', 'dumitru@romevents.ro', '40712345684', '$2y$10$Teszt1234', 7, 1, '2025-05-30 12:00:00', 1),
('Mihai', 'Stoica', 'stoica@romevents.ro', '40712345685', '$2y$10$Teszt1234', 7, 1, '2025-05-31 12:00:00', 1),
('Ana', 'Gheorghe', 'gheorghe@romevents.ro', '40712345686', '$2y$10$Teszt1234', 7, 1, '2025-06-01 12:00:00', 1),
('Dan', 'Rusu', 'rusu@romevents.ro', '40712345687', '$2y$10$Teszt1234', 7, 1, '2025-06-02 12:00:00', 1),
('Laura', 'Munteanu', 'munteanu@romevents.ro', '40712345688', '$2y$10$Teszt1234', 7, 1, '2025-06-03 12:00:00', 1),
('Gabriel', 'Serban', 'serban@romevents.ro', '40712345689', '$2y$10$Teszt1234', 7, 1, '2025-06-04 12:00:00', 1),
('Roxana', 'Dinu', 'dinu@romevents.ro', '40712345690', '$2y$10$Teszt1234', 7, 1, '2025-06-05 12:00:00', 1),
('Alexandru', 'Nistor', 'nistor@romevents.ro', '40712345691', '$2y$10$Teszt1234', 7, 1, '2025-06-06 12:00:00', 1),
('Ioana', 'Tudor', 'tudor@romevents.ro', '40712345692', '$2y$10$Teszt1234', 7, 1, '2025-06-07 12:00:00', 1),
('Bogdan', 'Marinescu', 'marinescu@romevents.ro', '40712345693', '$2y$10$Teszt1234', 7, 1, '2025-06-08 12:00:00', 1);

-- SerbiaEvents (free-trial - 2 felhasználó)
INSERT INTO `user` (`firstname`, `lastname`, `email`, `telephone`, `password`, `company_id`, `current_status_id`, `created_date`, `is_email_verified`) VALUES
('Marko', 'Petrovic', 'petrovic@serbiaevents.rs', '381601234568', '$2y$10$Teszt1234', 9, 1, '2025-05-30 14:00:00', 1);

-- Role kiosztások az új felhasználóknak
INSERT INTO `user_to_roles` (`user_id`, `role_id`) VALUES
-- EventVision CZ roles
((SELECT id FROM user WHERE email = 'novotny@eventvision.cz'), 1),
((SELECT id FROM user WHERE email = 'dvorak@eventvision.cz'), 2),
((SELECT id FROM user WHERE email = 'svoboda@eventvision.cz'), 3),
((SELECT id FROM user WHERE email = 'kral@eventvision.cz'), 4),
((SELECT id FROM user WHERE email = 'marek@eventvision.cz'), 5),
((SELECT id FROM user WHERE email = 'polak@eventvision.cz'), 6),
((SELECT id FROM user WHERE email = 'vesely@eventvision.cz'), 7),
((SELECT id FROM user WHERE email = 'horak@eventvision.cz'), 8),

-- RomanianEvents SRL roles
((SELECT id FROM user WHERE email = 'popescu@romevents.ro'), 1),
((SELECT id FROM user WHERE email = 'ionescu@romevents.ro'), 2),
((SELECT id FROM user WHERE email = 'popa@romevents.ro'), 3),
((SELECT id FROM user WHERE email = 'stan@romevents.ro'), 4),
((SELECT id FROM user WHERE email = 'radu@romevents.ro'), 5),
((SELECT id FROM user WHERE email = 'dumitru@romevents.ro'), 6),
((SELECT id FROM user WHERE email = 'stoica@romevents.ro'), 7),
((SELECT id FROM user WHERE email = 'gheorghe@romevents.ro'), 8),
((SELECT id FROM user WHERE email = 'rusu@romevents.ro'), 9),
((SELECT id FROM user WHERE email = 'munteanu@romevents.ro'), 10),
((SELECT id FROM user WHERE email = 'serban@romevents.ro'), 2),
((SELECT id FROM user WHERE email = 'dinu@romevents.ro'), 3),
((SELECT id FROM user WHERE email = 'nistor@romevents.ro'), 4),
((SELECT id FROM user WHERE email = 'tudor@romevents.ro'), 5),
((SELECT id FROM user WHERE email = 'marinescu@romevents.ro'), 6),

-- SerbiaEvents roles
((SELECT id FROM user WHERE email = 'petrovic@serbiaevents.rs'), 1);