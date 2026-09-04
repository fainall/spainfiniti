-- ════════════════════════════════════════════════════════════════
-- Las fotos salen de la base y pasan a ser archivos.
--
-- Al subir una foto desde el panel, la imagen entera quedaba guardada
-- como texto dentro de la fila del servicio. Eran 16 fotos, 1,2 MB, y
-- el sitio se las bajaba enteras en CADA carga de CADA pagina. En el
-- computador se nota poco; en un celular con datos la descarga se corta,
-- el sitio se queda sin catalogo y muestra la lista de respaldo, con
-- precios y descripciones viejos. Eso es lo que veia Luis.
--
-- Las mismas fotos ya estan subidas como archivos en /images/. Aqui la
-- base deja de guardar la imagen y guarda solo su direccion: la fila baja
-- de 90 KB a 40 bytes, y el navegador se guarda la foto en su cache un ano.
-- ════════════════════════════════════════════════════════════════

-- 14 servicios
update services   set img = '/images/servicios/plantillas-asd.jpg' where id = 'plantillas-asd';
update services   set img = '/images/servicios/atencion-podologica-integral.jpg' where id = 'atencion-podologica-integral';
update services   set img = '/images/servicios/limpieza-facial-profunda.jpg' where id = 'limpieza-facial-profunda';
update services   set img = '/images/servicios/plantillas-prueba-2.jpg' where id = 'plantillas-prueba-2';
update services   set img = '/images/servicios/cuidado-facial-spa-limpieza-facial-premium.jpg' where id = 'cuidado-facial-spa-limpieza-facial-premium';
update services   set img = '/images/servicios/desintoxicantes-drenaje-linftico-corporal-premium.jpg' where id = 'desintoxicantes-drenaje-linftico-corporal-premium';
update services   set img = '/images/servicios/tratamientos-corporales-infinity-anti-cellulite-pro.jpg' where id = 'tratamientos-corporales-infinity-anti-cellulite-pro';
update services   set img = '/images/servicios/tratamientos-corporales-infinity-body-sculpt.jpg' where id = 'tratamientos-corporales-infinity-body-sculpt';
update services   set img = '/images/servicios/desintoxicantes-drenaje-linftico-zona-especfica.jpg' where id = 'desintoxicantes-drenaje-linftico-zona-especfica';
update services   set img = '/images/servicios/tratamientos-corporales-infinity-slim-contour.jpg' where id = 'tratamientos-corporales-infinity-slim-contour';
update services   set img = '/images/servicios/tratamientos-corporales-infinity-reduccin-total-5x.jpg' where id = 'tratamientos-corporales-infinity-reduccin-total-5x';
update services   set img = '/images/servicios/podologia-clinica-tratamiento-con-cido-ntrico--alta-frecuencia---tipo-2.jpg' where id = 'podologia-clinica-tratamiento-con-cido-ntrico--alta-frecuencia---tipo-2';
update services   set img = '/images/servicios/podologia-clinica-desbaste-profesional-uas-con-hongos---tipo-2.jpg' where id = 'podologia-clinica-desbaste-profesional-uas-con-hongos---tipo-2';
update services   set img = '/images/servicios/podologia-clinica-ortonixia-de-las-uas-por-ua.jpg' where id = 'podologia-clinica-ortonixia-de-las-uas-por-ua';

-- 1 categoria
update categories set card_img = '/images/categorias/plantillas-card.jpg' where id = 'plantillas';
update categories set hero_img = '/images/categorias/plantillas-hero.jpg' where id = 'plantillas';

-- Comprobacion: no debe quedar ninguna foto guardada como texto
select 'services' as tabla, count(*) as fotos_en_texto from services   where img      like 'data:%'
union all select 'categories', count(*) from categories where card_img like 'data:%' or hero_img like 'data:%';
