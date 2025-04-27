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

-- 2. Felhasználók beszúrása (csomaghoz/módosításhoz illő létszám, role kiosztás, minden cégben legalább 1 tulajdonos, több usernél több role is lehet)
INSERT INTO `user` (`firstname`, `lastname`, `email`, `telephone`, `password`, `company_id`, `current_status_id`, `created_date`, `is_email_verified`) VALUES
('János', 'Kovács', 'kovacs@eventpro.hu', '06301234583', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, '2025-01-25 10:00:00', 1),
('László', 'Kiss', 'kiss.l@eventpro.hu', '06301234598', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, '2025-01-26 10:00:00', 1),
('Márta', 'Nagy', 'nagy.m@eventpro.hu', '06301234599', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, '2025-01-27 10:00:00', 1),
('József', 'Szabó', 'szabo.j@eventpro.hu', '06301234600', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, '2025-01-28 10:00:00', 1),
('Erzsébet', 'Tóth', 'toth.e@eventpro.hu', '06301234601', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, '2025-01-29 10:00:00', 1),
('Marek', 'Novak', 'novak@slovakstage.sk', '421901234568', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 1, '2025-05-10 09:00:00', 1),
('Jozef', 'Kral', 'kral@slovakstage.sk', '421901234569', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 1, '2025-05-11 09:00:00', 1),
('Lukas', 'Urban', 'urban@slovakstage.sk', '421901234570', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 2, 1, '2025-05-12 09:00:00', 1),
('Anna', 'Müller', 'mueller@buehneprofi.at', '4311234568', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, '2025-04-15 10:00:00', 1),
('Sophie', 'Schmidt', 'schmidt@buehneprofi.at', '4311234569', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 6, 1, '2025-04-16 10:00:00', 1),
('Péter', 'Nagy', 'nagy@stagetch.hu', '06301234584', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 1, '2025-01-28 14:30:00', 1),
('Gábor', 'Kiss', 'kiss@stagetch.hu', '06301234590', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 3, 1, '2025-01-29 14:30:00', 1),
('Anna', 'Kiss', 'kiss@soundwave.hu', '06301234585', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 5, 1, '2025-02-01 09:15:00', 1),
('Judit', 'Kiss', 'kiss@stagelight.hu', '06301234595', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 8, 1, '2025-04-01 13:25:00', 1),
('Tamás', 'Szabó', 'szabo@soundvisual.hu', '06301234596', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 8, 1, '2025-04-02 13:25:00', 1),
('Krisztina', 'Tóth', 'toth@eventstage.hu', '06301234597', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 9, 1, '2025-04-27 10:30:00', 1),
('Test', 'Trial', 'testtrial@testtrial.hu', '06309999998', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 10, 1, '2025-06-01 08:00:00', 1),
('Tibor', 'Fekete', 'fekete.tibor@eventpro.hu', '06301234610', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, '2025-01-30 10:00:00', 1),
('Zoltán', 'Fehér', 'feher.zoltan@eventpro.hu', '06301234611', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 1, 1, '2025-01-31 10:00:00', 1);

-- 3. Role kiosztás (user_to_roles)
INSERT INTO `user_to_roles` (`user_id`, `role_id`) VALUES
((SELECT id FROM user WHERE email = 'kovacs@eventpro.hu'), 1),
((SELECT id FROM user WHERE email = 'kovacs@eventpro.hu'), 8),
((SELECT id FROM user WHERE email = 'kiss.l@eventpro.hu'), 2),
((SELECT id FROM user WHERE email = 'nagy.m@eventpro.hu'), 3),
((SELECT id FROM user WHERE email = 'szabo.j@eventpro.hu'), 4),
((SELECT id FROM user WHERE email = 'toth.e@eventpro.hu'), 5),
((SELECT id FROM user WHERE email = 'fekete.tibor@eventpro.hu'), 6),
((SELECT id FROM user WHERE email = 'feher.zoltan@eventpro.hu'), 7),
((SELECT id FROM user WHERE email = 'novak@slovakstage.sk'), 1),
((SELECT id FROM user WHERE email = 'novak@slovakstage.sk'), 8),
((SELECT id FROM user WHERE email = 'kral@slovakstage.sk'), 2),
((SELECT id FROM user WHERE email = 'urban@slovakstage.sk'), 3),
((SELECT id FROM user WHERE email = 'mueller@buehneprofi.at'), 1),
((SELECT id FROM user WHERE email = 'nagy@stagetch.hu'), 1),
((SELECT id FROM user WHERE email = 'kiss@stagetch.hu'), 2),
((SELECT id FROM user WHERE email = 'kiss@soundwave.hu'), 1),
((SELECT id FROM user WHERE email = 'kiss@stagelight.hu'), 1),
((SELECT id FROM user WHERE email = 'szabo@soundvisual.hu'), 2),
((SELECT id FROM user WHERE email = 'toth@eventstage.hu'), 1),
((SELECT id FROM user WHERE email = 'testtrial@testtrial.hu'), 1);

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