/* ═══════════════════════════════════════════
   SPA INFINITY — Datos del sitio
   ═══════════════════════════════════════════ */

/* Base URL de imágenes locales del sitio */
const IMG = '/images/'

/* Los datos del negocio salen de config-cliente.js; si no cargara,
   se usan los de siempre. */
const _C = (typeof CLIENTE !== 'undefined') ? CLIENTE : {}
const SITE = {
  name: _C.nombre    || 'Spa Infinity',
  tagline: _C.rubro  || 'Centro Podológico & Spa',
  address: _C.direccion || 'Santo Domingo 1083, of. 502, Santiago',
  phone: _C.telefono || '+56 9 8668 8771',
  email: _C.correo || 'reservainfinity@spainfinity.cl',
  booking: (typeof waLink === 'function')
    ? waLink('Hola, quiero reservar una cita')
    : 'https://wa.me/56986688771?text=Hola,%20quiero%20reservar%20una%20cita',
  hours: 'Lun–Vie 10:00–20:00 · Sáb 11:00–18:00'
}

const CATEGORIES = [
  /* ── 1. PODOLOGÍA CLÍNICA ─────────────────────────── */
  {
    id: 'podologia-clinica',
    name: 'Podología Clínica',
    tagline: 'Salud y Bienestar',
    shortDesc: 'Atención integral, tratamientos preventivos y correctivos especializados para recuperar la salud y el confort de tus pies.',
    longDesc: 'Cuidado profesional y especializado para la salud de tus pies. Tratamientos clínicos avanzados realizados por especialistas para garantizar tu bienestar, alivio y comodidad en cada paso.',
    cardImg: IMG + 'podologia-clinica/podologia-clinica-hero.jpeg',
    heroImg: IMG + 'podologia-clinica/podologia-clinica-hero.jpeg',
    link:    'https://spainfinity.cl/service/',
    services: [
      {
        id: 'atencion-podologica-integral',
        catId: 'podologia-clinica',
        name: 'Atención Podológica Integral',
        tag: 'Cuidado Completo',
        price: "$25.600", duration: "60 min",
        shortDesc: "En 𝗦𝗽𝗮 𝗜𝗻𝗳𝗶𝗻𝗶𝘁𝘆 entregamos un cuidado completo y personalizado para tus 𝗣𝗶𝗲𝘀.",
        longDesc: "En 𝗦𝗽𝗮 𝗜𝗻𝗳𝗶𝗻𝗶𝘁𝘆 cuidamos tus piecitos con dedicación y cariño. Nuestra 𝗣𝗼𝗱𝗼𝗹𝗼𝗴𝗶́𝗮 𝗜𝗻𝘁𝗲𝗴𝗿𝗮𝗹 incluye evaluación, higiene, cuidado de uñas y piel, hidratación y un relajante masaje, para que tus pies luzcan saludables, suaves y se sientan renovados.",
        img: IMG + 'podologia-clinica/atencion-podologica-integral.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569265',
        includes: ['Evaluación clínica completa', 'Limpieza profunda', 'Corte y limado de uñas', 'Tratamiento de callosidades', 'Hidratación final'],
        highlighted: true
      },
      {
        id: 'podologia-esmaltado-permanente',
        catId: 'podologia-clinica',
        name: 'Podología Clínica + Esmaltado Permanente',
        tag: 'Salud & Estética',
        price: "$29.600", duration: "60 min",
        shortDesc: "La combinación perfecta entre un tratamiento podológico clínico riguroso y un acabado estético duradero con esmaltado permanente.",
        longDesc: "La combinación perfecta entre un tratamiento podológico clínico riguroso y un acabado estético duradero. Incluye todo el protocolo de Atención Podológica Integral más la aplicación de esmaltado permanente de alta durabilidad.",
        img: IMG + 'podologia-clinica/podologia-esmaltado-permanente.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569280',
        includes: ['Todo de Atención Podológica', 'Preparación de placa ungueal', 'Esmaltado permanente de alta calidad', 'Color a elección'],
      },
      {
        id: 'podologia-esmaltado-semipermanente',
        catId: 'podologia-clinica',
        name: 'Podología Clínica + Esmaltado Semipermanente',
        tag: 'Cuidado & Color',
        price: "$26.400", duration: "60 min",
        shortDesc: "Atención profesional integral sumada a un esmaltado semipermanente de alta calidad para proteger y embellecer tus uñas sin comprometer su salud.",
        longDesc: "Atención podológica integral y profesional combinada con un esmaltado semipermanente de alta calidad para proteger y embellecer tus uñas sin comprometer su salud. El equilibrio entre clínica y estética.",
        img: IMG + 'podologia-clinica/atencion-podologica-integral.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569280',
        includes: ['Todo de Atención Podológica', 'Esmaltado semipermanente', 'Preparación de placa ungueal'],
      },
      {
        id: 'una-encarnada-unilateral',
        catId: 'podologia-clinica',
        name: 'Podología Uña Encarnada Unilateral (por uña)',
        tag: 'Seguimiento Continuo',
        price: "$25.500", duration: "45 min",
        shortDesc: "Tratamiento especializado para uña encarnada unilateral con técnicas seguras de alivio y corrección.<br>\n<strong>Nota: El valor del servicio es por uña. Si el tratamiento presenta otras alteraciones o requerimientos adicionales, el valor puede variar.</strong>",
        longDesc: "En Spa Infinity nos aseguramos de que cada intervención se realice con rigurosidad clínica y técnicas estandarizadas, siempre priorizando tu bienestar y recuperación. Este tratamiento quirúrgico permite remover el borde de uña que se incrusta en la piel, aliviando el dolor y evitando complicaciones como infección o inflamación severa, garantizando un resultado seguro y controlado.",
        img: IMG + 'podologia-clinica/una-encarnada-unilateral.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569434',
        includes: ['Evaluación clínica del estado', 'Limpieza del surco afectado', 'Corte correctivo especializado', 'Aplicación de antiséptico'],
      },
      {
        id: 'una-encarnada-bilateral',
        catId: 'podologia-clinica',
        name: 'Podología Uña Encarnada Bilateral (por uña)',
        tag: 'Alivio Especializado',
        price: "$38.400", duration: "60 min",
        shortDesc: "Tratamiento clínico y extracción segura de uñas encarnadas bilaterales (onicocriptosis) para aliviar el dolor, reducir la inflamación y sanar la zona afectada.<br>\n<strong>Nota: El valor del servicio es por uña. Si el tratamiento presenta otras alteraciones o requerimientos adicionales, el valor puede variar.</strong>",
        longDesc: "Tratamiento clínico y extracción segura de uñas encarnadas bilaterales para aliviar el dolor, reducir la inflamación y sanar la zona afectada con protocolos de asepsia rigurosos.\nNota: El valor del servicio es por uña. Si el tratamiento presenta otras alteraciones o requerimientos adicionales, el valor puede variar.",
        img: IMG + 'podologia-clinica/una-encarnada-bilateral.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569310',
        includes: ['Evaluación clínica bilateral', 'Anestesia local si requerida', 'Extracción segura del fragmento', 'Curación y vendaje aséptico'],
      },
      {
        id: 'curacion-simple',
        catId: 'podologia-clinica',
        name: 'Curación Simple (30 min)',
        tag: 'Cuidado Terapéutico',
        price: "$8.800", duration: "20 min",
        shortDesc: "Procedimiento rápido y seguro diseñado para realizar curaciones de lesiones menores en los pies, asegurando higiene y pronta recuperación.",
        longDesc: "Procedimiento clínico rápido y seguro diseñado para realizar curaciones de lesiones menores en los pies, asegurando higiene, asepsia y pronta recuperación.",
        img: IMG + 'podologia-clinica/curacion-simple.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569335',
        includes: ['Evaluación de la lesión', 'Limpieza y asepsia', 'Curación con insumos clínicos', 'Vendaje protector'],
      },
      {
        id: 'hongos-avanzado',
        catId: 'podologia-clinica',
        name: 'Podología para Uñas con Hongos Avanzado (solo uñas)',
        tag: 'Higiene Profunda',
        price: "$21.900", duration: "45 min",
        shortDesc: "Sesión especializada para desbridar y realizar limpieza profunda en uñas afectadas por hongos. Para casos de 1 a 4 uñas afectadas<br>\n<strong>Nota: El servicio es solo en uñas.\nOtras patologías se requiere Atención Podológica Integral.</strong>",
        longDesc: "Sesión especializada para desbridar y realizar limpieza profunda en uñas afectadas por hongos (onicomicosis). Mejora la apariencia estética. Para casos de 1 a 4 uñas afectadas.",
        img: IMG + 'podologia-clinica/una-encarnada-bilateral.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569441',
        includes: ['Evaluación micológica', 'Desbridamiento de uñas afectadas', 'Limpieza profunda antifúngica', 'Aplicación de tratamiento tópico'],
      },
      {
        id: 'acido-nitrico',
        catId: 'podologia-clinica',
        name: 'Tratamiento con Ácido Nítrico (1 hr)',
        tag: 'Tratamiento Avanzado',
        price: "$27.000", duration: "60 min",
        shortDesc: "Aplicación clínica focalizada de ácido nítrico y alta frecuencia para combatir de manera agresiva y efectiva infecciones por hongos severos (onicomicosis). Para 1 - 4 uñas afectadas<br>\n<strong>Nota: Si el tratamiento presenta otras alteraciones o requerimientos adicionales, el valor puede variar.</strong>",
        longDesc: "En Spa Infinity realizamos el Tratamiento intensivo para hongos avanzados, que combina la aplicación controlada de ácido nítrico —altamente efectivo contra infecciones severas— y terapia de Alta Frecuencia para potenciar la acción antimicrobiana y acelerar la recuperación. Un protocolo seguro, preciso y acompañado de seguimiento profesional para lograr una uña más sana y libre de infección.",
        img: IMG + 'podologia-clinica/una-encarnada-bilateral.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569353',
        includes: ['Evaluación clínica de la infección', 'Preparación de la zona', 'Aplicación controlada de ácido nítrico', 'Indicaciones de cuidado post-tratamiento'],
      },
      {
        id: 'reconstruccion-ungual',
        catId: 'podologia-clinica',
        name: 'Reconstrucción Ungueal (1 hr)',
        tag: 'Restauración Estética',
        price: "$12.000", duration: "60 min",
        shortDesc: "Procedimiento de onicoplastia para la reconstrucción cosmética y funcional de uñas dañadas o perdidas, devolviéndoles su apariencia natural. Nota el valor es por uña.\nOtras patologías requiere Atención Podológica Integral.",
        longDesc: "Procedimiento de onicoplastia para la reconstrucción cosmética y funcional de uñas dañadas o perdidas, recuperando su aspecto natural y la resistencia que necesitan.",
        img: IMG + 'podologia-clinica/reconstruccion-ungual.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569345',
        includes: ['Evaluación de la uña dañada', 'Preparación de la placa ungueal', 'Modelado con material de reconstrucción', 'Acabado natural y pulido'],
      },
    ]
  },

  /* ── 2. MANICURE & PEDICURE SPA ─────────────────────── */
  {
    id: 'manicure-pedicure',
    name: 'Manicure & Pedicure',
    tagline: 'Belleza & Cuidado',
    shortDesc: 'Cuidado profundo de manos y pies con esmaltados de larga duración y acabados elegantes e impecables.',
    longDesc: 'Descubre el arte del cuidado perfecto para manos y pies. Nuestros especialistas combinan técnica y estética para ofrecerte resultados impecables que duran.',
    cardImg: IMG + 'manicure-pedicure/manicure-pedicure-hero.jpeg',
    heroImg: IMG + 'manicure-pedicure/manicure-pedicure-hero.jpeg',
    link:    'https://spainfinity.cl/manicure-y-pedicure-spa/',
    services: [
      {
        id: 'manicure-spa-permanente',
        catId: 'manicure-pedicure',
        name: 'Manicure Spa Permanente',
        tag: 'Larga Duración',
        price: "$18.000", duration: "60 min",
        shortDesc: "Cuidado de cutículas, limado, hidratación y esmaltado permanente Masglo para unas manos perfectas por semanas.",
        longDesc: "Manicure completo con evaluación de uñas, sanitización, limado, remoción de cutículas, exfoliación, masaje hidratante y aplicación de esmalte permanente de alta durabilidad.",
        img: IMG + 'Manicure Spa con Esmaltado Permanente.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569763',
        includes: ['Evaluación de uñas', 'Sanitización', 'Limado profesional', 'Remoción de cutículas', 'Exfoliación', 'Masaje hidratante', 'Esmaltado permanente Masglo'],
        highlighted: true
      },
      {
        id: 'manicure-spa-semipermanente',
        catId: 'manicure-pedicure',
        name: 'Manicure Spa Semipermanente',
        tag: 'Cuidado & Color',
        price: "$13.600", duration: "45 min",
        shortDesc: "Limpieza, limado, cuidado de cutículas y esmaltado semipermanente Masglo con resultados hasta 12 días.",
        longDesc: "Manicure spa con limpieza, limado, cuidado de cutículas y esmaltado semipermanente Masglo. Resultados duran hasta 12 días con acabado profesional y brillante.",
        img: IMG + 'Manicure Spa con Esmaltado Semipermanente.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569529',
        includes: ['Sanitización', 'Limado y forma', 'Remoción de cutículas', 'Esmaltado semipermanente Masglo'],
      },
      {
        id: 'pedicure-spa-permanente',
        catId: 'manicure-pedicure',
        name: 'Pedicure Spa Permanente',
        tag: 'Elegancia Duradera',
        price: "$23.800", duration: "60 min",
        shortDesc: "Pedicure completo con limpieza, limado, exfoliación, hidratación y esmaltado permanente de brillo intenso.",
        longDesc: "Pedicure spa completo con baño tibio relajante, corte de uñas, remoción de cutículas, eliminación de durezas, masaje hidratante y esmaltado permanente de larga duración.",
        img: IMG + 'Pedicure Spa con Esmaltado Permanente.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569848',
        includes: ['Baño tibio relajante', 'Corte de uñas', 'Remoción de cutículas', 'Eliminación de durezas', 'Masaje hidratante', 'Esmaltado permanente'],
      },
      {
        id: 'pedicure-spa-semipermanente',
        catId: 'manicure-pedicure',
        name: 'Pedicure Spa Semipermanente',
        tag: 'Cuidado Completo',
        price: "$18.700", duration: "45 min",
        shortDesc: "Pedicure completa con limpieza, limado, cuidado de cutículas, hidratación y esmaltado semipermanente de larga duración.",
        longDesc: "Pedicure spa con limpieza, limado, cuidado de cutículas, hidratación profunda y esmaltado semipermanente de brillo intenso y larga duración.",
        img: IMG + 'Pedicure Spa con Esmaltado Semipermanente.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569839',
        includes: ['Baño tibio relajante', 'Corte y limado de uñas', 'Remoción de cutículas', 'Hidratación', 'Esmaltado semipermanente'],
      },
      {
        id: 'pack-manicure-pedicure',
        catId: 'manicure-pedicure',
        name: 'Pack Manicure & Pedicure',
        tag: 'Pack Estrella',
        price: "$34.200", duration: "90 min",
        shortDesc: "Combina manicure semipermanente con pedicure permanente en una sola sesión de cuidado total.",
        longDesc: "Un pack diseñado para renovar tus manos y pies con técnicas profesionales, aromas suaves y una experiencia profundamente relajante. Incluye baño tibio, exfoliación, masaje hidratante y color de larga duración.",
        img: IMG + 'Pack Manicure Semipermanente + Pedicure Permanente.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569860',
        includes: ['Manicure semipermanente completo', 'Pedicure permanente completo', 'Exfoliación de manos y pies', 'Masaje hidratante'],
        highlighted: true
      },
      {
        id: 'retiro-esmaltado-limpieza',
        catId: 'manicure-pedicure',
        name: 'Retiro de Esmaltado + Limpieza',
        tag: 'Cuidado Seguro',
        price: "$13.500", duration: "30 min",
        shortDesc: "Retiro profesional de esmalte permanente combinado con limpieza de cutículas, dejando las uñas oxigenadas y preparadas.",
        longDesc: "Retiro profesional de esmalte permanente con técnicas y productos que protegen la salud de la uña natural, combinado con limpieza de cutículas. Deja las uñas oxigenadas y preparadas.",
        img: IMG + 'Retiro de Esmaltado + Limpieza.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569865',
        includes: ['Retiro seguro de esmalte permanente', 'Limpieza de cutículas', 'Hidratación de uñas', 'Oxigenación de placa ungueal'],
      },
    ]
  },

  /* ── 3. ADICIONALES MANI & PEDI ───────────────────── */
  {
    id: 'adicionales-mani-pedi',
    name: 'Adicionales Mani & Pedi',
    tagline: 'Complementos Exclusivos',
    shortDesc: 'Complementos ideales de esmaltado, exfoliación, hidratación y retiro seguro de productos para embellecer tus uñas.',
    longDesc: 'Servicios complementarios diseñados para potenciar o completar tu experiencia de manicure y pedicure con un toque extra de cuidado.',
    cardImg: IMG + 'adicionales-mani-pedi/adicionales-mani-pedi-hero.jpeg',
    heroImg: IMG + 'adicionales-mani-pedi/adicionales-mani-pedi-hero.jpeg',
    link:    'https://spainfinity.cl/adicionales-de-servicio-manicure-y-pedicure/',
    services: [
      {
        id: 'esmaltado-permanente',
        catId: 'adicionales-mani-pedi',
        name: 'Esmaltado Permanente (Manos o Pies)',
        tag: 'Brillo Duradero',
        price: "$7.900", duration: "15 min",
        shortDesc: "Acabado brillante, duradero y resistente con productos de alta calidad en manos o pies.",
        longDesc: "Aplicación de esmalte permanente de alta calidad en manos o pies para un acabado brillante, duradero y resistente. Perfecto como complemento a tu servicio de manicure o pedicure.",
        img: IMG + 'Esmaltado Permanente Manos.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569951',
        includes: ['Preparación de la uña', 'Aplicación de base', 'Esmaltado permanente a elección', 'Sellado con lámpara UV'],
      },
      {
        id: 'esmaltado-semipermanente',
        catId: 'adicionales-mani-pedi',
        name: 'Esmaltado Semipermanente (Manos o Pies)',
        tag: 'Color Express',
        price: "$4.900", duration: "20 min",
        shortDesc: "Aplicación de esmalte semipermanente con productos de alta calidad para un acabado radiante y profesional.",
        longDesc: "Aplicación rápida de esmalte semipermanente con productos de alta calidad para un acabado radiante y profesional en manos o pies.",
        img: IMG + 'Esmaltado Permanente Pies (Esmaltado Semi-Permanente).jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569956',
        includes: ['Preparación de la uña', 'Aplicación de color semipermanente', 'Secado rápido', 'Acabado brillante'],
      },
      {
        id: 'exfoliacion-manos-pies',
        catId: 'adicionales-mani-pedi',
        name: 'Exfoliación Manos o Pies',
        tag: 'Renovación',
        price: "$2.900", duration: "15 min",
        shortDesc: "Tratamiento renovador que elimina células muertas, mejora la textura de la piel y promueve hidratación profunda.",
        longDesc: "Tratamiento renovador que elimina células muertas, mejora la textura de la piel. Promueve hidratación profunda y suavidad instantánea en manos o pies.",
        img: IMG + 'Exfoliación Manos o Pies.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569907',
        includes: ['Aplicación de exfoliante', 'Masaje circular renovador', 'Retiro de células muertas', 'Hidratación final'],
      },
      {
        id: 'parafina-wax',
        catId: 'adicionales-mani-pedi',
        name: 'Hidratación con Parafina Wax (Manos o Pies)',
        tag: 'Nutrición Profunda',
        price: "$9.900", duration: "15 min",
        shortDesc: "Nutrición profunda con calor suave que mejora la circulación, abre los poros y deja la piel suave y rejuvenecida.",
        longDesc: "Parafina tibia que envuelve manos o pies, abre poros para la absorción de nutrientes y deja la piel suave y rejuvenecida. Nutrición profunda con calor suave que mejora la circulación.",
        img: IMG + 'Hidratación con Parafina Wax.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569964',
        includes: ['Inmersión en parafina tibia', 'Envoltura térmica', 'Absorción de nutrientes', 'Retiro e hidratación final'],
        highlighted: true
      },
      {
        id: 'retiro-esmaltado',
        catId: 'adicionales-mani-pedi',
        name: 'Retiro de Esmaltado Simple',
        tag: 'Cuidado Seguro',
        price: "$4.000", duration: "15 min",
        shortDesc: "Retiro total y seguro de esmalte permanente. Servicio rápido y profesional protegiendo la salud de las uñas.",
        longDesc: "Retiro total y seguro de esmalte permanente. Servicio rápido y profesional enfocado en remover el esmalte protegiendo la salud de las uñas. No incluye limpieza de cutículas.",
        img: IMG + 'Retiro de Esmaltado.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569870',
        includes: ['Aplicación de removedor', 'Tiempo de acción controlado', 'Retiro suave del esmalte', 'Limpieza de la uña'],
      },
      {
        id: 'retiro-polygel-acrilico',
        catId: 'adicionales-mani-pedi',
        name: 'Retiro de Rubber, Polygel o Acrílico',
        tag: 'Remoción Profesional',
        price: "$7.000", duration: "25 min",
        shortDesc: "Remoción profesional de Rubber, Polygel o Acrílico sin causar daño, dejando las uñas limpias y preparadas.",
        longDesc: "Remoción profesional de Rubber, Polygel o Acrílico. Elimina el producto sin causar daño, dejando las uñas limpias y suaves, preparadas para nuevos diseños o tratamientos.",
        img: IMG + 'Retiro de Rubber, Polygel o Acrílico.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569946',
        includes: ['Limado del producto', 'Aplicación de removedor profesional', 'Retiro delicado sin daño', 'Hidratación de la uña natural'],
      },
    ]
  },

  /* ── 4. TRATAMIENTOS CORPORALES ───────────────────── */
  {
    id: 'tratamientos-corporales',
    name: 'Tratamientos Corporales',
    tagline: 'Cuerpo & Bienestar',
    shortDesc: 'Técnicas avanzadas de exfoliación, drenaje linfático y aparatología estética para moldear y revitalizar tu cuerpo.',
    longDesc: 'Descubre nuestros tratamientos corporales especializados, diseñados para esculpir, tonificar y revitalizar tu cuerpo con tecnología de última generación y técnicas manuales expertas.',
    cardImg: IMG + 'tratamientos-corporales/tratamientos-corporales-hero.jpeg',
    heroImg: IMG + 'tratamientos-corporales/tratamientos-corporales-hero.jpeg',
    link:    'https://spainfinity.cl/tratamientos-corporales/',
    services: [
      {
        id: 'sesion-inicial-exfoliacion-drenaje',
        catId: 'tratamientos-corporales',
        name: 'Sesión Inicial — Exfoliación y Drenaje Linfático Cuerpo Completo',
        tag: 'Primera Sesión',
        price: "$36.800", duration: "60 min",
        shortDesc: "Exfoliación profunda seguida de drenaje linfático manual que estimula la circulación, reduce la retención de líquidos y promueve la eliminación de toxinas.",
        longDesc: "Exfoliación profunda para eliminar impurezas y células muertas, seguida de drenaje linfático manual que estimula la circulación, reduce la retención de líquidos y promueve la eliminación natural de toxinas. Tratamiento de cuerpo completo.",
        img: IMG + 'Sesión Inicial – Exfoliación y Drenaje Linfático Cuerpo Completo.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2570119',
        includes: ['Exfoliación corporal completa', 'Drenaje linfático manual', 'Estimulación circulatoria', 'Hidratación post-tratamiento'],
        highlighted: true
      },
      {
        id: 'tratamiento-corporal-aparatologia',
        catId: 'tratamientos-corporales',
        name: 'Tratamiento Corporal Personalizado con Aparatología',
        tag: 'Tecnología Avanzada',
        price: "$36.400", duration: "70 min",
        shortDesc: "Tratamiento personalizado con aparatología estética avanzada (cavitación, vacuun, radiofrecuencia u ondas rusas) según diagnóstico profesional.",
        longDesc: "Tratamiento corporal personalizado con tecnología estética avanzada seleccionada según diagnóstico profesional. Incluye cavitación, vacuun, radiofrecuencia u ondas rusas para reducción de grasa localizada, tonificación muscular y reafirmación de tejidos.",
        img: IMG + 'Sesión individual tratamiento corporal con aparatología estética.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2570122',
        includes: ['Diagnóstico profesional personalizado', 'Selección de aparatología según zona', 'Aplicación de tecnología avanzada', 'Evaluación de resultados'],
      },
    ]
  },

  /* ── 5. DESINTOXICANTES ───────────────────────────── */
  {
    id: 'desintoxicantes',
    name: 'Desintoxicantes',
    tagline: 'Pureza & Equilibrio',
    shortDesc: 'Terapias diseñadas para purificar el organismo, eliminar toxinas, reducir retención de líquidos y equilibrar tu energía.',
    longDesc: 'Rituales y terapias de desintoxicación que limpian tu cuerpo desde adentro, restaurando el equilibrio y la vitalidad con técnicas naturales y activos purificantes.',
    cardImg: IMG + 'desintoxicantes/desintoxicantes-hero.jpeg',
    heroImg: IMG + 'desintoxicantes/desintoxicantes-hero.jpeg',
    link:    'https://spainfinity.cl/desintoxicacion-ionica/',
    services: [
      {
        id: 'desintoxicacion-pediluvio-ionico',
        catId: 'desintoxicantes',
        name: 'Desintoxicación Pediluvio Iónico',
        tag: 'Purificación',
        price: "$13.600", duration: "30 min",
        shortDesc: "Inmersión tibia en pediluvio iónico con sales minerales, diseñada para depurar impurezas, reactivar la energía y renovar la ligereza corporal",
        longDesc: "Baño de pies iónico con agua tibia, sales minerales y un sistema de ionización que genera una suave corriente eléctrica. Ayuda al cuerpo a liberar impurezas por los poros, promueve la oxigenación celular, mejora la circulación y fortalece el sistema inmunológico.",
        img: IMG + 'Desintoxicación Pediluvio Iónico.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2570133',
        includes: ['Preparación del baño con sales minerales', 'Inmersión con ionización activa', 'Liberación de impurezas por los poros', 'Secado e hidratación'],
        highlighted: true
      },
      {
        id: 'desintoxicacion-ionica-reflexologia',
        catId: 'desintoxicantes',
        name: 'Desintoxicación Iónica + Reflexología Podal',
        tag: 'Detox & Relajación',
        price: "$23.200", duration: "70 min",
        shortDesc: "Combina el pediluvio iónico con reflexología en puntos de presión del pie conectados a órganos internos para relajación profunda.",
        longDesc: "Combina la terapia de pediluvio iónico para eliminar toxinas con técnicas de reflexología en puntos específicos de presión del pie conectados a órganos internos. Promueve relajación profunda y alivio de tensiones.",
        img: IMG + 'Desintoxicación Iónica + Reflexología Podal.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2570176',
        includes: ['Pediluvio iónico completo', 'Reflexología en puntos de presión', 'Estimulación de órganos internos', 'Relajación profunda guiada'],
      },
    ]
  },

  /* ── 6. DEPILACIÓN ────────────────────────────────── */
  {
    id: 'depilacion',
    name: 'Depilación',
    tagline: 'Suavidad & Precisión',
    shortDesc: 'Extracción eficaz y delicada con cera de alta calidad para todas las zonas, logrando una piel suave por más tiempo.',
    longDesc: 'Depilación profesional con ceras de alta calidad para resultados duraderos y una piel increíblemente suave. Tratamos todas las zonas con precisión y el mayor cuidado.',
    cardImg: IMG + 'depilacion/depilacion-hero.jpeg',
    heroImg: IMG + 'depilacion/depilacion-hero.jpeg',
    link:    'https://spainfinity.cl/depilacion-facial-corporal/',
    services: [
      {
        id: 'depilacion-facial',
        catId: 'depilacion',
        name: 'Depilación Facial (Rostro Completo)',
        tag: 'Rostro Luminoso',
        price: "$16.000", duration: "30 min",
        shortDesc: "Eliminación delicada y efectiva del vello facial para un rostro limpio, luminoso y perfectamente cuidado.",
        longDesc: "Eliminación delicada y efectiva del vello facial para dejar el rostro limpio, luminoso y perfectamente cuidado. Técnicas suaves y productos especializados para piel sensible.",
        img: IMG + 'Depilación Facial (rostro completo).jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569978',
        includes: ['Limpieza previa de la zona', 'Aplicación de cera especial facial', 'Extracción delicada del vello', 'Crema calmante post-depilación'],
      },
      {
        id: 'depilacion-zonas-faciales',
        catId: 'depilacion',
        name: 'Depilación por Zonas Faciales (Patillas, Bozo, Nariz)',
        tag: 'Precisión & Detalle',
        price: "$6.000", duration: "30 min",
        shortDesc: "Eliminación precisa de vello facial en zonas específicas para una apariencia limpia, natural y definida.",
        longDesc: "Eliminación precisa de vello facial en zonas específicas (patillas, bozo y nariz) para una apariencia limpia, natural y definida.",
        img: IMG + 'Depilación por zonas Patillas, Bozo, Nariz.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569982',
        includes: ['Selección de zona a tratar', 'Aplicación de cera de precisión', 'Extracción focalizada', 'Hidratación calmante'],
      },
      {
        id: 'depilacion-axilas',
        catId: 'depilacion',
        name: 'Depilación Axilas',
        tag: 'Rápido & Efectivo',
        price: "$7.900", duration: "15 min",
        shortDesc: "Depilación con cera de alta calidad para una extracción eficaz, dejando la piel limpia y tersa.",
        longDesc: "Depilación con cera de alta calidad para una extracción eficaz, dejando la piel limpia y tersa. Resultados duran varias semanas.",
        img: IMG + 'Depilación Axilas.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569986',
        includes: ['Preparación de la piel', 'Aplicación de cera caliente', 'Extracción rápida y eficaz', 'Producto calmante final'],
      },
      {
        id: 'depilacion-medio-brazo',
        catId: 'depilacion',
        name: 'Depilación Medio Brazo',
        tag: 'Zona Intermedia',
        price: "$11.200", duration: "30 min",
        shortDesc: "Remoción de vello precisa y delicada para un acabado limpio, uniforme y de larga duración.",
        longDesc: "Remoción de vello precisa y delicada para un acabado limpio, uniforme y de larga duración. Extracción desde la raíz cuidando la piel.",
        img: IMG + 'Depilación Medio Brazo.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569989',
        includes: ['Preparación de la piel', 'Depilación con cera premium', 'Extracción desde la raíz', 'Hidratación post-depilación'],
      },
      {
        id: 'depilacion-brazo-completo',
        catId: 'depilacion',
        name: 'Depilación Brazo Completo',
        tag: 'Cobertura Completa',
        price: "$15.800", duration: "35 min",
        shortDesc: "Eliminación del vello desde el hombro hasta la muñeca con cera de alta calidad para piel suave y uniforme.",
        longDesc: "Eliminación del vello desde el hombro hasta la muñeca con cera de alta calidad. Piel suave, uniforme en tono, con confort libre de vello por varias semanas.",
        img: IMG + 'Depilación Brazo Completo.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569993',
        includes: ['Preparación de la piel', 'Depilación hombro a muñeca', 'Extracción con cera de alta calidad', 'Crema hidratante calmante'],
      },
      {
        id: 'depilacion-media-pierna',
        catId: 'depilacion',
        name: 'Depilación Media Pierna',
        tag: 'Piernas Tersas',
        price: "$12.800", duration: "40 min",
        shortDesc: "Extracción efectiva y delicada con cera de calidad para piernas tersas y limpias por semanas.",
        longDesc: "Extracción efectiva y delicada con cera de calidad para piernas tersas y limpias. Resultados duran varias semanas.",
        img: IMG + 'Depilación Media Pierna.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569998',
        includes: ['Preparación de la piel', 'Depilación rodilla a tobillo', 'Extracción con cera premium', 'Hidratación post-depilación'],
      },
      {
        id: 'depilacion-pierna-completa',
        catId: 'depilacion',
        name: 'Depilación Pierna Completa',
        tag: 'Máxima Suavidad',
        price: "$18.900", duration: "45 min",
        shortDesc: "Eliminación eficaz del vello con cera de alta calidad para piernas completamente suaves y duraderas.",
        longDesc: "Eliminación eficaz del vello con cera de alta calidad para piernas suaves. Resultados duraderos, piel suave y limpia.",
        img: IMG + 'Depilación Pierna Completa.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569999',
        includes: ['Preparación de la piel', 'Depilación completa muslo a tobillo', 'Extracción con cera de alta calidad', 'Crema calmante e hidratante'],
      },
      {
        id: 'depilacion-bikini',
        catId: 'depilacion',
        name: 'Depilación Bikini',
        tag: 'Cuidado Íntimo',
        price: "$11.900", duration: "30 min",
        shortDesc: "Retiro del vello con cera de manera delicada y precisa, cuidando la piel para dejarla suave y limpia.",
        longDesc: "Retiro del vello con cera de manera delicada y precisa, cuidando la piel para dejarla suave, limpia y con sensación de frescura y bienestar.",
        img: IMG + 'Depilación Bikini.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2570004',
        includes: ['Preparación y limpieza de la zona', 'Depilación delicada con cera', 'Extracción precisa del vello', 'Producto calmante post-depilación'],
      },
      {
        id: 'depilacion-brasilero',
        catId: 'depilacion',
        name: 'Depilación Brasilero / Rebaje',
        tag: 'Depilación Íntima',
        price: "$16.800", duration: "45 min",
        shortDesc: "Cera premium y técnicas profesionales para una depilación íntima duradera, precisa y con acabado impecable.",
        longDesc: "Cera premium y técnicas profesionales para una depilación íntima duradera, precisa y con acabado impecable. Piel suave libre de irritación.",
        img: IMG + 'Depilación BrasileroRebaje.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2570009',
        includes: ['Preparación y limpieza de la zona', 'Depilación con cera premium', 'Técnica profesional anti-irritación', 'Hidratación y cuidado final'],
      },
    ]
  },

  /* ── 7. CUIDADO FACIAL SPA ────────────────────────── */
  {
    id: 'cuidado-facial-spa',
    name: 'Cuidado Facial Spa',
    tagline: 'Luminosidad & Juventud',
    shortDesc: 'Limpiezas profundas y tratamientos revitalizantes con tecnología estética para un rostro luminoso y saludable.',
    longDesc: 'Tratamientos faciales especializados que combinan técnicas manuales y tecnología avanzada para revitalizar, rejuvenecer y dar luminosidad a tu piel.',
    cardImg: IMG + 'cuidado-facial-spa/cuidado-facial-spa-hero.jpeg',
    heroImg: IMG + 'cuidado-facial-spa/cuidado-facial-spa-hero.jpeg',
    link:    'https://spainfinity.cl/cuidado-facial-spa/',
    services: [
      {
        id: 'limpieza-facial-profunda',
        catId: 'cuidado-facial-spa',
        name: 'Limpieza Facial Profunda con Aparatología',
        tag: 'Pureza & Luminosidad',
        price: "$29.400", duration: "60 min",
        shortDesc: "Limpieza facial personalizada con tecnología avanzada (paleta ultrasónica, vaporozono, máscara LED) y principios activos según tu tipo de piel para una tez limpia y radiante.",
        longDesc: "Limpieza facial profunda personalizada combinando tecnología avanzada (paleta ultrasónica, vaporozono, máscara LED) y principios activos seleccionados según tu tipo de piel para una tez limpia, luminosa y saludable.",
        img: IMG + 'Limpieza Facial Profunda.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569880',
        includes: ['Paleta ultrasónica', 'Alta frecuencia', 'Radiofrecuencia', 'Martillo frío', 'Máscara LED', 'Principios activos personalizados'],
        highlighted: true
      },
      {
        id: 'limpieza-espalda',
        catId: 'cuidado-facial-spa',
        name: 'Limpieza de Espalda',
        tag: 'Cuidado Corporal',
        price: "$31.200", duration: "60 min",
        shortDesc: "Limpieza profesional de espalda con exfoliación, extracción, masaje relajante e hidratación profunda.",
        longDesc: "Limpieza profesional de espalda con exfoliación, extracción de comedones, masaje relajante e hidratación profunda. Elimina impurezas y renueva la piel.",
        img: IMG + 'Limpieza de Espalda.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569889',
        includes: ['Exfoliación de espalda', 'Extracción de comedones', 'Masaje relajante', 'Hidratación profunda'],
      },
    ]
  },

  /* ── 8. MASAJES TERAPÉUTICOS ──────────────────────── */
  {
    id: 'masajes-terapeuticos',
    name: 'Masajes Terapéuticos',
    tagline: 'Relajación & Alivio',
    shortDesc: 'Relajación profunda, alivio de tensiones y bienestar integral a través de técnicas expertas y piedras calientes.',
    longDesc: 'Nuestros masajes terapéuticos están diseñados para liberar tensiones, aliviar el dolor muscular y reconectar cuerpo y mente en una experiencia de bienestar total.',
    cardImg: IMG + 'masajes-terapeuticos/masajes-terapeuticos-hero.png',
    heroImg: IMG + 'masajes-terapeuticos/masajes-terapeuticos-hero.png',
    link:    'https://spainfinity.cl/masajes-terapeuticos/',
    services: [
      {
        id: 'masaje-piedras-calientes',
        catId: 'masajes-terapeuticos',
        name: 'Masaje con Piedras Calientes (60 min)',
        tag: 'Calor Terapéutico',
        price: "$27.000", duration: "60 min",
        shortDesc: "Tratamiento terapéutico con calor volcánico que alivia tensiones, reduce el estrés y mejora la circulación.",
        longDesc: "Tratamiento terapéutico con piedras volcánicas calientes aplicadas en puntos estratégicos del cuerpo. Alivia tensiones, reduce el estrés y mejora la circulación.",
        img: IMG + 'Masaje con Piedras Calientes 60 min.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569481',
        includes: ['Calentamiento de piedras volcánicas', 'Colocación en puntos estratégicos', 'Masaje con piedras calientes', 'Relajación y estiramiento final'],
        highlighted: true
      },
      {
        id: 'masaje-piedras-express',
        catId: 'masajes-terapeuticos',
        name: 'Masaje con Piedras Express (30 min)',
        tag: 'Calor Express',
        price: "$16.500", duration: "30 min",
        shortDesc: "Equilibrio del calor volcánico en sesión concentrada para liberar tensiones y renovar cuerpo y mente.",
        longDesc: "Equilibrio del calor volcánico en sesión concentrada para liberar tensiones, mejorar la circulación y renovar cuerpo y mente.",
        img: IMG + 'Masaje con Piedras Calientes 30 min.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569478',
        includes: ['Piedras volcánicas en zonas clave', 'Masaje concentrado con calor', 'Alivio rápido de tensiones'],
      },
      {
        id: 'masaje-descontracturante',
        catId: 'masajes-terapeuticos',
        name: 'Masaje Descontracturante (60 min)',
        tag: 'Alivio Muscular',
        price: "$32.900", duration: "60 min",
        shortDesc: "Terapia de presión profunda para deshacer nudos, aliviar sobrecargas musculares y mejorar movilidad.",
        longDesc: "Terapia de presión profunda para deshacer nudos, aliviar sobrecargas musculares y mejorar movilidad. Cubre espalda, cuello, hombros, zona lumbar, piernas y brazos.",
        img: IMG + 'Masaje Descontracturante 60 min.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569471',
        includes: ['Evaluación de zonas tensas', 'Presión profunda en nudos musculares', 'Trabajo en espalda, cuello y hombros', 'Estiramiento y relajación final'],
      },
      {
        id: 'masaje-descontracturante-express',
        catId: 'masajes-terapeuticos',
        name: 'Masaje Descontracturante Express (30 min)',
        tag: 'Alivio Rápido',
        price: "$19.900", duration: "30 min",
        shortDesc: "Técnicas específicas en cuello, espalda y hombros para reducir contracturas y restaurar flexibilidad.",
        longDesc: "Técnicas específicas en cuello, espalda y hombros para reducir contracturas, mejorar circulación y restaurar flexibilidad.",
        img: IMG + 'Masaje Descontracturante 30 min.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569464',
        includes: ['Focalización en zonas críticas', 'Presión profunda en cuello y hombros', 'Liberación de contracturas'],
      },
      {
        id: 'masaje-relax',
        catId: 'masajes-terapeuticos',
        name: 'Masaje Relax (60 min)',
        tag: 'Bienestar Total',
        price: "$25.600", duration: "60 min",
        shortDesc: "Movimientos suaves y envolventes que favorecen la oxigenación, relajando profundamente y liberando el estrés.",
        longDesc: "Movimientos suaves y envolventes que favorecen la oxigenación, relajando profundamente. Libera la tensión acumulada y el estrés diario.",
        img: IMG + 'Masaje Relax 60 min.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569457',
        includes: ['Ambientación relajante', 'Masaje de cuerpo completo', 'Movimientos suaves y envolventes', 'Relajación profunda final'],
      },
      {
        id: 'masaje-relax-express',
        catId: 'masajes-terapeuticos',
        name: 'Masaje Relax Express (30 min)',
        tag: 'Relajación Rápida',
        price: "$16.200", duration: "30 min",
        shortDesc: "Alivia el estrés diario con técnicas suaves y terapéuticas para un descanso reparador.",
        longDesc: "Alivia el estrés diario con técnicas suaves y terapéuticas para descanso reparador, mejora la circulación y relaja los músculos.",
        img: IMG + 'Masaje Relax 30 min.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2476891',
        includes: ['Técnicas suaves en zonas clave', 'Relajación muscular', 'Mejora de circulación'],
      },
      {
        id: 'sesion-premium-mixtas',
        catId: 'masajes-terapeuticos',
        name: 'Sesión Premium Técnicas Mixtas (70 min)',
        tag: 'Experiencia Premium',
        price: "$33.600", duration: "70 min",
        shortDesc: "Sesión integral que combina aromaterapia, maniobras de relajación, terapia descontracturante, piedras calientes y un masaje facial–craneal para liberar tensiones, equilibrar el cuerpo y ofrecer un bienestar profundo y renovador.",
        longDesc: "Digitopresión, aromaterapia y masaje descontracturante combinados en un tratamiento personalizado. Libera tensión muscular, mejora circulación y restaura equilibrio energético.",
        img: IMG + 'Masaje Sesión Premium Técnicas Mixtas 70 min.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569461',
        includes: ['Digitopresión en puntos de tensión', 'Aromaterapia personalizada', 'Masaje descontracturante profundo', 'Restauración del equilibrio energético'],
      },
      {
        id: 'reflexologia-podal',
        catId: 'masajes-terapeuticos',
        name: 'Reflexología Podal (45 min)',
        tag: 'Equilibrio Natural',
        price: "$18.000", duration: "30 min",
        shortDesc: "Estimulación natural de zonas reflejas en los pies para aliviar el estrés y promover bienestar integral.",
        longDesc: "Estimulación natural de zonas reflejas en los pies para aliviar el estrés, mejorar la circulación y promover bienestar integral.",
        img: IMG + 'Reflexología Podal sesión 45 min.jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2569492',
        includes: ['Baño relajante de pies', 'Estimulación de zonas reflejas', 'Presión en puntos conectados a órganos', 'Masaje final de relajación'],
      },
    ]
  },

  /* ── 9. PESTAÑAS & CEJAS ──────────────────────────── */
  {
    id: 'pestanas-cejas',
    name: 'Pestañas & Cejas',
    tagline: 'Mirada & Expresión',
    shortDesc: 'Realza tu mirada con tratamientos de lifting, ondulación, tinte, bótox y perfilación armónica de cejas.',
    longDesc: 'Realza la belleza natural de tu mirada con nuestros tratamientos especializados para pestañas y cejas. Resultados naturales, duraderos y perfectamente armonizados.',
    cardImg: IMG + 'pestanas-cejas/pestanas-cejas-hero.jpeg',
    heroImg: IMG + 'pestanas-cejas/pestanas-cejas-hero.jpeg',
    link:    'https://spainfinity.cl/lifting-belleza-de-pestanas/',
    services: [
      {
        id: 'lifting-tinte-botox',
        catId: 'pestanas-cejas',
        name: 'Lifting + Tinte + Bótox',
        tag: 'Curvatura & Volumen',
        price: "$21.000", duration: "60 min",
        shortDesc: "En Spa Infinity, realzamos tu mirada con nuestro tratamiento de lifting de pestañas, tinte y bótox. Luce pestañas más curvadas, definidas y oscuras, con un acabado natural, elegante y sofisticado.",
        longDesc: "En Spa Infinity, descubre una mirada más intensa, abierta y luminosa con nuestro exclusivo tratamiento de lifting de pestañas, tinte y bótox. Esta combinación realza la curvatura natural de tus pestañas, aporta un tono más definido y ayuda a mantenerlas nutridas y cuidadas.",
        img: IMG + 'Lifting de Pestañas + Tinte + Bótox (Efecto Curvatura y Volumen).jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2570014',
        includes: ['Lifting desde la raíz', 'Tinte intensificador de color', 'Bótox nutritivo e hidratante', 'Fijación y acabado natural'],
        highlighted: true
      },
      {
        id: 'ondulacion-tinte-botox',
        catId: 'pestanas-cejas',
        name: 'Ondulación + Tinte + Bótox',
        tag: 'Elegancia & Brillo',
        price: "$22.800", duration: "60 min",
        shortDesc: "Curvatura suave y natural, color intenso con tinte y tratamiento botánico que nutre y fortalece cada pestaña.",
        longDesc: "Curvatura suave y natural, color intenso con tinte, y tratamiento botánico fortificante que nutre y fortalece cada pestaña.",
        img: IMG + 'Ondulación de Pestañas + Tinte + Bótox (Efecto Elegancia y Brillo).jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2570102',
        includes: ['Ondulación suave y natural', 'Tinte de color intenso', 'Bótox fortificante', 'Acabado con brillo'],
      },
      {
        id: 'pack-mirada-expresiva',
        catId: 'pestanas-cejas',
        name: 'Pack Mirada Expresiva',
        tag: 'Pack Completo',
        price: "$30.000", duration: "70 min",
        shortDesc: "Incluye lifting u ondulación de pestañas, para lograr una curvatura perfecta y natural; perfilación de cejas, que define y armoniza tus facciones; y fijación con tinte y bótox, que aporta color, nutrición y una forma impecable.",
        longDesc: "Spa Infinity presenta el Pack Mirada Expresiva, un tratamiento completo diseñado para realzar tu belleza natural y destacar tu mirada con un acabado elegante y duradero.\n\nIncluye lifting u ondulación de pestañas, para lograr una curvatura perfecta y natural; perfilación de cejas, que define y armoniza tus facciones; y tinte y bótox, que aportan color, nutrición y una apariencia impecable.",
        img: IMG + 'Pack Mirada Expresiva (Pestañas y Cejas Perfectas).jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2570108',
        includes: ['Lifting u ondulación de pestañas', 'Perfilación de cejas', 'Tinte intensificador', 'Bótox nutritivo'],
      },
      {
        id: 'mantenimiento-lifting',
        catId: 'pestanas-cejas',
        name: 'Mantenimiento Lifting/Ondulación (40 días)',
        tag: 'Retoque & Cuidado',
        price: '$19.900', duration: '50 min',
        shortDesc: 'Refresca la curvatura y el cuidado de las pestañas para conservar su forma, fuerza y brillo a los 40 días.',
        longDesc: 'Refresca la curvatura y el cuidado de las pestañas para conservar su forma, fuerza y brillo. Servicio de retoque a los 40 días del tratamiento inicial.',
        img: IMG + 'Mantenimiento Lifting u Ondulación (Efecto Natural y Fresco).jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2570113',
        includes: ['Retoque de curvatura', 'Renovación de tinte', 'Aplicación de bótox nutritivo'],
      },
      {
        id: 'perfilacion-cejas',
        catId: 'pestanas-cejas',
        name: 'Perfilación de Cejas',
        tag: 'Precisión & Definición',
        price: "$14.400", duration: "20 min",
        shortDesc: "Diseño y perfilado según la estructura del rostro para un acabado equilibrado y armónico que enmarca los ojos.",
        longDesc: "Diseño y perfilado según la estructura del rostro para un acabado equilibrado y armónico. Elimina exceso de vello para un contorno limpio que enmarca los ojos.",
        img: IMG + 'Perfilación de Cejas (Precisión y Definición).jpeg',
        link: 'https://spainfinity.site.agendapro.com/cl/sucursal/452261?services_id=2570111',
        includes: ['Diseño según estructura facial', 'Depilación de exceso de vello', 'Perfilado con precisión', 'Acabado armónico'],
      },
    ]
  },
]

/* ── PROMOTIONS / EVENTS ────────────────────────────── */
const PROMOS = []

/* ── Apply admin overrides from localStorage ──────── */
;(function applyAdminOverrides() {
  try {
    const raw = localStorage.getItem('spa_admin_data')
    if (!raw) return
    const ov = JSON.parse(raw)

    if (ov.site) Object.assign(SITE, ov.site)

    if (ov.categories) {
      for (const cat of CATEGORIES) {
        const co = ov.categories[cat.id]
        if (co) {
          const svcs = cat.services          // preserve services array
          Object.assign(cat, co)
          cat.services = svcs
        }
      }
    }

    if (ov.services) {
      for (const cat of CATEGORIES) {
        for (let i = 0; i < cat.services.length; i++) {
          const so = ov.services[cat.services[i].id]
          if (so) Object.assign(cat.services[i], so)
        }
      }
    }

    // New categories added from admin
    if (ov.newCategories) {
      for (const nc of ov.newCategories) {
        if (!CATEGORIES.find(c => c.id === nc.id)) {
          CATEGORIES.push(nc)
        }
      }
    }

    // New services added to existing categories
    if (ov.newServices) {
      for (const [catId, svcs] of Object.entries(ov.newServices)) {
        const cat = CATEGORIES.find(c => c.id === catId)
        if (cat) {
          for (const ns of svcs) {
            if (!cat.services.find(s => s.id === ns.id)) {
              cat.services.push(ns)
            }
          }
        }
      }
    }

    // Promotions / Events
    if (ov.promos) {
      PROMOS.length = 0
      ov.promos.forEach(p => PROMOS.push(p))
    }
  } catch (e) { console.warn('Admin overrides error:', e) }
})()

/* Helper */
function getCategoryById(id) {
  return CATEGORIES.find(c => c.id === id)
}
/* la foto que se muestra de un servicio: la suya, o la de su categoria */
function imgServicio(s) { return (s && (s.img || s.imgCat)) || '' }

function getServiceById(id) {
  for (const cat of CATEGORIES) {
    const svc = cat.services.find(s => s.id === id)
    if (svc) return { service: svc, category: cat }
  }
  return null
}
function getActivePromoForService(svcId) {
  return PROMOS.find(p => p.active && p.serviceIds && p.serviceIds.includes(svcId))
}

/* ── Supabase live data loader ─────────────────────── */
let _supabaseReady = false
let _supabasePromise = null
async function initSupabaseData() {
  if (_supabaseReady) return true
  if (_supabasePromise) return _supabasePromise
  if (typeof loadFromSupabase !== 'function') return false
  _supabasePromise = (async () => {
    try {
      const cats = await loadFromSupabase()
      if (!cats || !cats.length) return false
      // Replace CATEGORIES contents with Supabase data
      CATEGORIES.length = 0
      cats.forEach(c => CATEGORIES.push(c))
      _supabaseReady = true
      console.log('✓ Data loaded from Supabase:', CATEGORIES.length, 'categories')
      // Notify pages so they can re-render with fresh data
      window.dispatchEvent(new CustomEvent('categories-updated'))
      return true
    } catch (e) {
      console.warn('Supabase init failed, using static data:', e)
      return false
    }
  })()
  return _supabasePromise
}

/* Auto-init if supabase-client.js loaded before data.js */
if (typeof loadFromSupabase === 'function') {
  initSupabaseData()
}
