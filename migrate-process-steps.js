/* ═══════════════════════════════════════════
   ONE-TIME MIGRATION: Populate process_steps
   Run in browser console on admin page
   ═══════════════════════════════════════════ */

const PROCESS_STEPS_DATA = {
  'atencion-podologica-integral': [
    { title: 'Evaluación y Limpieza', text: 'Comenzamos con una evaluación clínica detallada y una limpieza profunda, paso fundamental para asegurar pies sanos y cómodos.' },
    { title: 'Tratamiento Clínico', text: 'Corte y limado profesional de uñas, atención especializada a callosidades y tratamiento de uñas encarnadas.' },
    { title: 'Cuidado Dermatológico', text: 'Atendemos alteraciones dérmicas para restaurar la salud integral de tu piel.' }
  ],
  'podologia-esmaltado-permanente': [
    { title: 'Atención Profesional', text: 'Evaluación clínica del pie, limpieza de uñas y surcos ungueales, corte técnico profesional, eliminación de durezas superficiales e hidratación profunda.' },
    { title: 'Esmaltado de Alta Calidad', text: 'Aplicamos esmaltado permanente reconocido por su durabilidad y brillo. Color vibrante por más tiempo y apariencia elegante sin comprometer la salud de la uña.' },
    { title: 'Protocolos de Bioseguridad', text: 'Trabajamos con insumos descartables e instrumental esterilizado. El esmaltado se realiza únicamente en uñas sanas, previniendo riesgos o infecciones.' }
  ],
  'podologia-esmaltado-semipermanente': [
    { title: 'Atención Integral', text: 'Evaluación clínica del pie, limpieza de uñas y surcos ungueales, corte técnico profesional, eliminación de durezas superficiales e hidratación profunda.' },
    { title: 'Esmaltado Masglo', text: 'Aplicamos esmalte semipermanente de alta calidad. Brinda un color vibrante por más tiempo, aportando mayor resistencia al desgarre y un acabado siempre impecable.' },
    { title: 'Cuidado Profesional', text: 'Diseñado con protocolos especializados para proteger y embellecer tus uñas sin comprometer su salud, sumado a recomendaciones personalizadas para el cuidado diario.' }
  ],
  'una-encarnada-unilateral': [
    { title: 'Alivio Inmediato', text: 'Tratamiento clínico altamente especializado, enfocado directamente en aliviar el dolor y reducir la inflamación desde la primera sesión.' },
    { title: 'Solución de Raíz', text: 'Intervención profesional para tratar y eliminar la causa del problema de forma segura, restaurando la comodidad de la zona afectada.' },
    { title: 'Salud y Prevención', text: 'Cuidado clínico diseñado para devolverle la salud y bienestar total a tus pies, aplicando protocolos preventivos para evitar futuras molestias.' }
  ],
  'una-encarnada-bilateral': [
    { title: 'Alivio Inmediato', text: 'Tratamiento clínico altamente especializado, enfocado directamente en aliviar el dolor y reducir la inflamación desde la primera sesión.' },
    { title: 'Solución de Raíz', text: 'Intervención profesional para tratar y eliminar la causa del problema de forma segura, restaurando la comodidad de la zona afectada.' },
    { title: 'Salud y Prevención', text: 'Cuidado clínico diseñado para devolverle la salud y bienestar total a tus pies, aplicando protocolos preventivos para evitar futuras molestias.' }
  ],
  'curacion-simple': [
    { title: 'Limpieza y Desinfección', text: 'Procedimiento clínico enfocado en mantener el área tratada libre de impurezas, previniendo cualquier tipo de infección tras la intervención de la uña.' },
    { title: 'Control de Cicatrización', text: 'Supervisión profesional y constante del avance de la herida, asegurando que el tejido se regenere de manera óptima y saludable.' },
    { title: 'Recuperación Segura', text: 'Te acompañamos en el proceso post-tratamiento para garantizar una sanación rápida, sin complicaciones y con el máximo confort para tus pies.' }
  ],
  'hongos-avanzado': [
    { title: 'Eliminación de Tejido', text: 'Procedimiento profesional que elimina con precisión el exceso de tejido afectado por hongos tanto en las uñas como en la piel.' },
    { title: 'Mejora Estética', text: 'Mejora inmediatamente la apariencia y textura de las uñas, devolviéndoles un aspecto mucho más limpio, uniforme y cuidado.' },
    { title: 'Regeneración Saludable', text: 'El tratamiento favorece la regeneración natural de la uña, ayudándote a recuperar la salud y la estética integral de tus pies de forma duradera.' }
  ],
  'acido-nitrico': [
    { title: 'Acción Directa', text: 'El Ácido Nítrico actúa directamente sobre la lámina ungueal afectada, penetrando profundamente para eliminar la infección micótica desde su origen.' },
    { title: 'Sin Dolor ni Riesgos', text: 'Procedimiento clínicamente diseñado, altamente efectivo sin generar dolor, permitiendo recuperar la salud de los pies de forma completamente segura.' },
    { title: 'Crecimiento Saludable', text: 'Además de eliminar los hongos, el tratamiento promueve la regeneración celular, ayudando a restaurar uñas limpias, fuertes y con apariencia natural.' }
  ],
  'reconstruccion-ungual': [
    { title: 'Restauración Estética', text: 'Devuelve la forma y apariencia natural a la uña que ha sufrido daños, logrando un acabado estético impecable.' },
    { title: 'Protección Funcional', text: 'Crea una barrera protectora segura que favorece el correcto crecimiento de la uña tras haber pasado por traumatismos.' },
    { title: 'Salud y Bienestar', text: 'Un tratamiento diseñado no solo para embellecer, sino para mejorar la salud integral de la lámina ungueal.' }
  ],
  'manicure-spa-permanente': [
    { title: 'Preparación Integral', text: 'Evaluación de uñas y piel, limpieza y sanitización de manos, corte, limado y definición de la forma de las uñas, remoción de cutículas, exfoliación suave y masaje hidratante.' },
    { title: 'Esmaltado Permanente', text: 'Aplicación de esmalte permanente de larga duración con color intenso, brillo uniforme y resistencia superior al desgaste diario.' },
    { title: 'Toque Spa e Hidratación', text: 'Nutrimos las manos para dejarlas suaves y frescas, logrando un acabado elegante que realza tu estilo personal.' }
  ],
  'manicure-spa-semipermanente': [
    { title: 'Limpieza y Preparación', text: 'Evaluación de uñas y piel, limpieza y sanitización de manos, corte, limado y definición, exfoliación, masaje y aplicación de esmalte.' },
    { title: 'Esmaltado Masglo', text: 'Aplicación de esmaltado semipermanente de alta duración que ofrece un color intenso, un brillo uniforme y una resistencia superior.' },
    { title: 'Belleza Duradera', text: 'Resultados que duran hasta por 12 días, utilizando productos de alta calidad que cuidan, protegen y fortalecen la uña natural.' }
  ],
  'pedicure-spa-permanente': [
    { title: 'Cuidado y Exfoliación', text: 'Evaluación de uñas y piel, baño tibio relajante, corte y limado, remoción de cutículas, eliminación de durezas, exfoliación y masaje hidratante.' },
    { title: 'Hidratación Profunda', text: 'Tratamientos hidratantes de alta calidad que restauran la elasticidad y frescura de los pies.' },
    { title: 'Esmaltado Permanente', text: 'Aplicación de esmaltado permanente de brillo intenso y larga duración para uñas impecables y un acabado elegante.' }
  ],
  'pedicure-spa-semipermanente': [
    { title: 'Cuidado y Exfoliación', text: 'Evaluación de uñas y piel, baño tibio relajante, corte y limado, remoción de cutículas, eliminación de durezas, exfoliación y masaje hidratante.' },
    { title: 'Hidratación Profunda', text: 'Tratamientos hidratantes de alta calidad que restauran la elasticidad y frescura de los pies.' },
    { title: 'Esmaltado Semipermanente', text: 'Aplicación de esmaltado semipermanente de brillo intenso para uñas impecables con un acabado elegante y natural.' }
  ],
  'pack-manicure-pedicure': [
    { title: 'Cuidado y Bienestar', text: 'Evaluación de uñas y piel, baño tibio relajante, corte, limado y definición, remoción de cutículas, eliminación de durezas, exfoliación y masaje hidratante.' },
    { title: 'Color y Estilo', text: 'Selección de colores variados y diseños personalizados, con aplicación de esmalte para un resultado brillante y a medida.' },
    { title: 'Larga Duración', text: 'Garantizamos hasta 3 semanas de frescura, elegancia y resistencia sin preocuparte por retoques, ideal para tu vida diaria y ocasiones especiales.' }
  ],
  'retiro-esmaltado-limpieza': [
    { title: 'Retiro Seguro', text: 'Eliminamos el esmalte permanente utilizando técnicas y productos adecuados que protegen la salud e integridad de tu uña natural.' },
    { title: 'Limpieza de Cutículas', text: 'Realizamos un despeje y limpieza minuciosa de la zona de la cutícula, promoviendo un contorno limpio y estéticamente cuidado.' },
    { title: 'Renovación Total', text: 'Al finalizar, tus manos o pies quedarán completamente libres de impurezas, oxigenados y listos para descansar o recibir un nuevo tratamiento.' }
  ],
  'esmaltado-permanente': [
    { title: 'Alta Calidad', text: 'Aplicamos productos y esmaltes de primera categoría, garantizando una excelente cobertura.' },
    { title: 'Brillo y Resistencia', text: 'Consigue un acabado radiante y duradero. Nuestro esmaltado está diseñado para resistir el día a día.' },
    { title: 'Cuidado Natural', text: 'Además de embellecer, nos enfocamos en proteger la estructura de tu uña, manteniendo su salud y belleza original.' }
  ],
  'esmaltado-semipermanente': [
    { title: 'Alta Calidad', text: 'Aplicamos productos y esmaltes semi-permanentes de primera categoría, garantizando una excelente cobertura sobre la uña natural.' },
    { title: 'Brillo y Resistencia', text: 'Consigue un acabado radiante. Nuestro esmaltado está diseñado para resistir el día a día manteniendo un brillo impecable.' },
    { title: 'Cuidado Natural', text: 'Además de embellecer, nos enfocamos en proteger la estructura de tu uña, manteniendo siempre su salud y belleza original intacta.' }
  ],
  'exfoliacion-manos-pies': [
    { title: 'Renovación Celular', text: 'Eliminamos suavemente las células muertas de tus manos o pies, promoviendo la regeneración natural y mejorando visiblemente la textura de tu piel.' },
    { title: 'Hidratación Profunda', text: 'Al preparar la piel mediante la exfoliación, favorecemos una absorción óptima de los nutrientes, logrando una hidratación profunda y duradera.' },
    { title: 'Suavidad y Frescura', text: 'Disfruta de resultados inmediatos. Ideal para mantener tus manos y pies siempre saludables, con un aspecto radiante y suavidad incomparable.' }
  ],
  'parafina-wax': [
    { title: 'Calor Terapéutico', text: 'Aplicamos parafina cálida que envuelve tus manos o pies, proporcionando un calor suave que relaja los músculos y mejora la circulación sanguínea.' },
    { title: 'Nutrición Profunda', text: 'El tratamiento abre los poros permitiendo que los nutrientes penetren en las capas más profundas, hidratando intensamente incluso la piel más seca.' },
    { title: 'Sensación de Bienestar', text: 'Tus manos o pies quedarán increíblemente suaves, rejuvenecidos y con una sensación de relajación y bienestar total que perdurará a lo largo del día.' }
  ],
  'retiro-esmaltado': [
    { title: 'Retiro Seguro', text: 'Eliminamos el esmalte permanente utilizando técnicas y productos adecuados que protegen la integridad y estructura de tu uña natural.' },
    { title: 'Proceso Rápido', text: 'Un procedimiento ágil y eficiente enfocado exclusivamente en la remoción total del esmalte anterior para dejar la uña completamente limpia.' },
    { title: 'Uñas Listas', text: 'Dejamos tus uñas libres de producto y preparadas para descansar, respirar o para recibir tu próximo tratamiento de belleza y color.' }
  ],
  'retiro-polygel-acrilico': [
    { title: 'Cuidado Profesional', text: 'Realizamos el retiro de Rubber, Polygel o Acrílico utilizando técnicas seguras que evitan cualquier tipo de daño.' },
    { title: 'Salud Natural', text: 'Nos aseguramos de proteger la integridad estructural de tus uñas en todo momento, manteniéndolas fuertes y sanas.' },
    { title: 'Listas para Renovar', text: 'Eliminamos el producto por completo, dejando tus uñas limpias, suaves y perfectamente preparadas para recibir su próximo tratamiento.' }
  ],
  'sesion-inicial-exfoliacion-drenaje': [
    { title: 'Exfoliación Profunda', text: 'Comenzamos con una exfoliación profunda que elimina impurezas y células muertas, dejando tu piel increíblemente suave, renovada y luminosa.' },
    { title: 'Drenaje Linfático', text: 'Aplicamos un drenaje manual que estimula la circulación, reduce la retención de líquidos y favorece la eliminación natural de toxinas.' },
    { title: 'Bienestar Total', text: 'El resultado es una piel revitalizada, un cuerpo notablemente más ligero y una profunda sensación de bienestar desde tu primera sesión.' }
  ],
  'tratamiento-corporal-aparatologia': [
    { title: 'Diagnóstico Previo', text: 'Cada cuerpo es único. Evaluamos tu zona a tratar para elegir la tecnología adecuada y personalizar el enfoque.' },
    { title: 'Aparatología Avanzada', text: 'Empleamos equipos de última generación para abordar grasa localizada, mejorar el tono muscular y reafirmar tejidos con seguridad y eficacia.' },
    { title: 'Resultados Visibles', text: 'Mejora significativamente la textura de la piel y redefine tu silueta, con cambios notorios desde las primeras sesiones.' }
  ],
  'desintoxicacion-pediluvio-ionico': [
    { title: 'Purificación Interna', text: 'Durante la sesión, los pies se sumergen en un baño de agua tibia con sales minerales y un sistema de ionización que genera una suave corriente eléctrica.' },
    { title: 'Beneficios Físicos', text: 'Esta reacción promueve la liberación de impurezas a través de los poros, favoreciendo la oxigenación celular, la circulación y fortaleciendo el sistema inmunológico.' },
    { title: 'Bienestar Integral', text: 'Contribuye al bienestar emocional y energético, proporcionando una sensación de ligereza, relajación profunda y renovación desde la primera sesión.' }
  ],
  'desintoxicacion-ionica-reflexologia': [
    { title: 'Pediluvio Iónico', text: 'Comenzamos con un baño tibio de sales minerales y un sistema de ionización que ayuda a eliminar toxinas, mejorar la circulación y preparar tu cuerpo.' },
    { title: 'Reflexología Podal', text: 'Aplicamos suaves presiones en puntos específicos de los pies conectados con tus órganos, logrando favorecer la relajación profunda y aliviar tensiones.' },
    { title: 'Renovación Integral', text: 'El resultado es un estado de armonía y bienestar holístico, donde tu cuerpo, mente y espíritu se equilibran, mejorando tu energía vital natural.' }
  ],
  'depilacion-facial': [
    { title: 'Cuidado Delicado', text: 'Eliminamos el vello de forma suave y efectiva, utilizando técnicas y productos diseñados para proteger la alta sensibilidad de tu piel facial.' },
    { title: 'Efectividad Total', text: 'Un tratamiento completo diseñado para dejar tu rostro libre de vello, garantizando una extracción precisa que prolonga la sensación de limpieza.' },
    { title: 'Piel Luminosa', text: 'Consigue una apariencia impecable, tersa y perfectamente cuidada. Ideal para realzar tu luminosidad natural y sentirte fresca y radiante todos los días.' }
  ],
  'depilacion-zonas-faciales': [
    { title: 'Precisión y Cuidado', text: 'Eliminamos el vello no deseado con técnicas precisas y delicadas, especialmente diseñadas para proteger y respetar la piel sensible de tu rostro.' },
    { title: 'Apariencia Impecable', text: 'Logramos una limpieza total en zonas clave como patillas, bozo y nariz, manteniendo tu rostro siempre radiante, uniforme y suave al tacto.' },
    { title: 'Acabado Natural', text: 'Disfruta de un perfil facial definido y completamente natural, libre de vello y con una sensación de frescura que te encantará lucir todos los días.' }
  ],
  'depilacion-axilas': [
    { title: 'Extracción Eficaz', text: 'Utilizamos cera de alta calidad para garantizar una extracción del vello rápida y efectiva desde la raíz, logrando resultados impecables.' },
    { title: 'Cuidado Delicado', text: 'Un proceso profesional diseñado para ser sumamente amable con tu piel, reduciendo al máximo la irritación en esta zona tan sensible.' },
    { title: 'Frescura Duradera', text: 'Disfruta de unas axilas completamente limpias, tersas y con una sensación de frescura, suavidad y libertad que se mantiene por semanas.' }
  ],
  'depilacion-medio-brazo': [
    { title: 'Extracción Precisa', text: 'Removemos el vello de la zona de medio brazo de forma minuciosa y delicada, garantizando una eliminación efectiva del vello desde la raíz.' },
    { title: 'Acabado Uniforme', text: 'Nuestro método profesional cuida tu piel en todo momento, logrando un acabado completamente limpio, terso, uniforme y libre de irritaciones.' },
    { title: 'Larga Duración', text: 'Disfruta de la comodidad y confianza de lucir una piel suave y perfectamente cuidada con resultados que perduran por mucho más tiempo.' }
  ],
  'depilacion-brazo-completo': [
    { title: 'Cobertura Total', text: 'Eliminamos el vello desde el hombro hasta la muñeca de manera delicada y precisa, utilizando cera de alta calidad para garantizar una extracción eficaz.' },
    { title: 'Piel Suave y Uniforme', text: 'Logramos una extracción eficaz desde la raíz para dejar tu piel con una textura increíblemente suave y un tono completamente uniforme.' },
    { title: 'Frescura Prolongada', text: 'Disfruta de la comodidad y seguridad de unos brazos libres de vello, reduciendo la irritación y manteniendo una sensación de frescura por semanas.' }
  ],
  'depilacion-media-pierna': [
    { title: 'Extracción Efectiva', text: 'Aplicamos cera de alta calidad para garantizar una extracción del vello rápida y efectiva desde la raíz en la zona de media pierna.' },
    { title: 'Cuidado Delicado', text: 'Un proceso profesional y minucioso diseñado para cuidar tu piel, reduciendo la irritación y dejándola completamente limpia y tersa.' },
    { title: 'Acabado Duradero', text: 'Disfruta de la comodidad y seguridad de lucir unas piernas suaves, libres de vello y con una sensación de frescura que dura por semanas.' }
  ],
  'depilacion-pierna-completa': [
    { title: 'Eliminación Eficaz', text: 'Utilizamos cera de alta calidad para lograr una eliminación eficaz del vello desde la raíz, garantizando resultados duraderos y precisos en toda la pierna.' },
    { title: 'Piel Tersa y Limpia', text: 'Nuestro método profesional cuida tu piel durante el proceso, dejándola increíblemente suave, uniforme y libre de vello tras cada sesión.' },
    { title: 'Frescura Prolongada', text: 'Disfruta de la comodidad y confianza de lucir unas piernas perfectamente cuidadas y con una sensación de frescura por mucho más tiempo.' }
  ],
  'depilacion-bikini': [
    { title: 'Delicadeza y Precisión', text: 'Retiramos el vello con cera de manera minuciosa, asegurando una extracción eficaz y cuidando al máximo la sensibilidad de tu piel en todo momento.' },
    { title: 'Piel Suave y Limpia', text: 'Depilación profesional que minimiza la irritación, logrando una textura increíblemente suave.' },
    { title: 'Frescura y Bienestar', text: 'Comodidad duradera y sensación de bienestar personal tras el tratamiento.' }
  ],
  'depilacion-brasilero': [
    { title: 'Cuidado y Confort', text: 'Utilizamos cera premium y técnicas profesionales diseñadas para minimizar las molestias.' },
    { title: 'Piel Suave y Limpia', text: 'Logramos una extracción precisa del vello para dejar tu piel increíblemente suave y libre de irritación.' },
    { title: 'Acabado Impecable', text: 'Ideal para quienes buscan resultados precisos y duraderos.' }
  ],
  'limpieza-espalda': [
    { title: 'Renovación Profunda', text: 'Iniciamos con una exfoliación profesional para eliminar células muertas e impurezas, preparando tu piel para una extracción profunda y efectiva.' },
    { title: 'Extracción de Comedones', text: 'Realizamos una cuidadosa extracción de comedones para liberar los poros de imperfecciones, mejorando significativamente la textura de tu espalda.' },
    { title: 'Hidratación y Masaje', text: 'Finalizamos con una hidratación profunda y un masaje relajante que dejará tu espalda increíblemente suave, limpia y con un aspecto saludable.' }
  ],
  'limpieza-facial-profunda': [
    { title: 'Limpieza y Exfoliación', text: 'Utilizamos paleta ultrasónica para eliminar células muertas y limpiar los poros en profundidad sin irritar tu piel, preparándola para el tratamiento.' },
    { title: 'Aparatología Avanzada', text: 'Aplicamos altafrecuencia para oxigenar y desinfectar, y radiofrecuencia para activar la producción de colágeno, mejorando firmeza y elasticidad.' },
    { title: 'Calma y Revitalización', text: 'Finalizamos con martillo frío para descongestionar y cerrar los poros, junto a una máscara LED terapéutica que potencia resultados visibles y duraderos.' }
  ],
  'masaje-piedras-calientes': [
    { title: 'Calor Volcánico', text: 'Durante la sesión se colocan piedras volcánicas en puntos estratégicos del cuerpo. El calor penetra en los músculos, ayudando a aliviar tensiones y mejorar la circulación.' },
    { title: 'Relajación Profunda', text: 'Combina la aplicación de piedras calientes con técnicas y maniobras suaves y envolventes que potencian la relajación muscular y reducen el estrés significativamente.' },
    { title: 'Equilibrio Energético', text: 'Ideal para aplicarse en espalda, cuello, hombros, piernas y brazos. Brinda una experiencia reconfortante que disminuye la rigidez y restaura tu equilibrio interior.' }
  ],
  'masaje-piedras-express': [
    { title: 'Calor Volcánico', text: 'Durante la sesión se colocan piedras volcánicas en puntos estratégicos del cuerpo.' },
    { title: 'Equilibrio Energético', text: 'La aplicación de estas piedras en puntos clave permite equilibrar la energía corporal, brindándote una sensación de calma y bienestar total.' },
    { title: 'Renovación Rápida', text: 'Terapia eficiente de 30 minutos diseñada para desconectar de la rutina diaria, refrescar el estado físico y mental, y restaurar la vitalidad.' }
  ],
  'masaje-descontracturante': [
    { title: 'Presión Focalizada', text: 'Trabajamos de manera profunda en las zonas con mayor tensión, aplicando presión firme, amasamientos intensos y liberación miofascial para abordar nudos musculares.' },
    { title: 'Cobertura Completa', text: 'Técnicas aplicadas en espalda alta y baja, cuello, hombros, zona lumbar, piernas y brazos para una recuperación muscular integral.' },
    { title: 'Alivio y Ligereza', text: 'Alivio y mejora de movilidad, restauración de flexibilidad y liberación del estrés.' }
  ],
  'masaje-descontracturante-express': [
    { title: 'Técnicas Específicas', text: 'Aplicamos técnicas manuales especializadas y enfocadas en disolver nudos y reducir las molestas contracturas musculares de forma efectiva y segura.' },
    { title: 'Alivio Focalizado', text: 'Trabajamos directamente sobre la rigidez y las molestias en zonas críticas de alta tensión acumulada, como el cuello, la espalda y los hombros.' },
    { title: 'Flexibilidad y Bienestar', text: 'Promovemos una mejor circulación sanguínea y aumentamos la flexibilidad muscular, restaurando tu movilidad y bienestar general en solo 30 minutos.' }
  ],
  'masaje-relax': [
    { title: 'Liberación de Tensiones', text: 'Diseñado especialmente para liberar tensiones acumuladas y reducir el estrés diario, logrando revitalizar por completo tanto tu cuerpo como tu mente.' },
    { title: 'Técnicas Envolventes', text: 'Mediante técnicas suaves, precisas y envolventes, favorecemos tu circulación y oxigenación, llevándote a un estado de relajación muscular profunda.' },
    { title: 'Descanso y Renovación', text: 'Experiencia de descanso completo y reconexión personal durante la hora completa.' }
  ],
  'masaje-relax-express': [
    { title: 'Alivio del Estrés', text: 'Diseñado específicamente para liberar la tensión acumulada, aliviar el estrés diario y relajar de forma completa.' },
    { title: 'Técnicas Suaves', text: 'A través de movimientos suaves, rítmicos y técnicas terapéuticas especializadas, logramos mejorar tu circulación.' },
    { title: 'Descanso Profundo', text: 'Te ayudamos a restaurar el equilibrio natural entre tu cuerpo y tu mente, ofreciéndote una experiencia de descanso.' }
  ],
  'sesion-premium-mixtas': [
    { title: 'Técnicas Mixtas', text: 'Combina lo mejor de diversas técnicas como el masaje descontracturante, digitopresión y aromaterapia, adaptadas a las necesidades individuales.' },
    { title: 'Liberación Profunda', text: 'Trabajo profundo para liberar tensión muscular acumulada, mejorar la circulación y aliviar eficazmente las zonas de mayor tensión.' },
    { title: 'Equilibrio Energético', text: 'Restaura el equilibrio natural del cuerpo, entregando relajación profunda y renovación física y mental completa.' }
  ],
  'reflexologia-podal': [
    { title: 'Zonas Reflejas', text: 'Estimulamos puntos específicos en los pies conectados con distintos órganos mediante presiones controladas y técnicas altamente especializadas.' },
    { title: 'Alivio Profundo', text: 'Un tratamiento profundamente relajante que ayuda a disminuir las tensiones acumuladas, aliviar el estrés diario y mejorar tu circulación general.' },
    { title: 'Armonía Total', text: 'Favorecemos el correcto funcionamiento del organismo para que al finalizar experimentes una sensación inigualable de descanso, armonía y revitalización.' }
  ],
  'lifting-tinte-botox': [
    { title: 'Elevación y Curvatura', text: 'El lifting eleva y curva tus pestañas desde la raíz, creando un hermoso efecto de mayor longitud y abriendo el área del ojo dramáticamente.' },
    { title: 'Color y Profundidad', text: 'Aplicamos un tinte especial que intensifica el color natural de tus pestañas, aportando definición y profundidad sin necesidad de usar rímel.' },
    { title: 'Nutrición de Botox', text: 'El botox nutritivo hidrata y fortalece cada pestaña, mejorando su estructura y brillo natural para un resultado impecable que dura hasta 6 semanas.' }
  ],
  'ondulacion-tinte-botox': [
    { title: 'Curvatura Elegante', text: 'La ondulación aporta una curvatura suave, elegante y perfectamente natural a tus pestañas, abriendo tu mirada desde el primer instante.' },
    { title: 'Color Definido', text: 'Aplicamos un tinte especial que realza el tono de tus pestañas, logrando un efecto mucho más definido y profundo sin necesidad de usar rímel.' },
    { title: 'Vitalidad y Botox', text: 'El botox nutre y fortalece cada hebra capilar, dejando tus pestañas más sanas, brillantes y resistentes para lucir radiantes por semanas.' }
  ],
  'pack-mirada-expresiva': [
    { title: 'Curvatura Perfecta', text: 'El lifting u ondulación de pestañas logra una curvatura perfecta y natural, elevando tus pestañas desde la raíz para abrir y transformar tu mirada.' },
    { title: 'Diseño y Armonía', text: 'Incluye una perfilación de cejas experta que define, enmarca y armoniza las facciones de tu rostro, aportando un equilibrio perfecto a tu expresión.' },
    { title: 'Color y Nutrición', text: 'Finalizamos con fijación de tinte y botox, aportando un color intenso, nutrición profunda y una forma impecable para lucir siempre arreglada sin esfuerzo.' }
  ],
  'mantenimiento-lifting': [
    { title: 'Refresca la Curvatura', text: 'Retocamos y refrescamos la curvatura de tus pestañas para que conserven esa forma elevada y perfectamente definida por mucho más tiempo.' },
    { title: 'Fuerza y Brillo', text: 'Aplicamos cuidados específicos que ayudan a conservar la fuerza, hidratación y el brillo natural de cada pestaña, manteniéndolas siempre saludables.' },
    { title: 'Resultados Prolongados', text: 'Tratamiento ideal para realizar a los 40 días, diseñado para prolongar la vida útil del servicio original y seguir luciendo una mirada radiante.' }
  ],
  'perfilacion-cejas': [
    { title: 'Diseño Personalizado', text: 'Mediante técnicas precisas, estudiamos la estructura natural de tu rostro para crear un diseño de cejas completamente adaptado a tus facciones únicas.' },
    { title: 'Definición y Armonía', text: 'Retiramos el vello excedente logrando un contorno limpio, equilibrado y armónico que enmarca perfectamente tus ojos y resalta tu expresión.' },
    { title: 'Mirada Expresiva', text: 'Disfruta de un acabado profesional que realza tu belleza natural, otorgando mayor intensidad y expresividad a tu mirada para lucir impecable todos los días.' }
  ]
}

// Also add missing services that may have different IDs or need drenaje services
const ADDITIONAL_STEPS = {
  'drenaje-linfatico-completo': [
    { title: 'Técnica Especializada', text: 'Aplicamos movimientos lentos, rítmicos y precisos que activan la circulación linfática de manera suave, sin generar dolor ni presión profunda.' },
    { title: 'Múltiples Beneficios', text: 'Esta técnica ayuda a desinflamar tejidos, mejorar la circulación, aliviar la pesadez y fortalecer el sistema inmunológico de todo el organismo.' },
    { title: 'Bienestar Integral', text: 'Se aplica en piernas, abdomen, brazos y rostro según tu necesidad. Al finalizar, experimentarás una profunda sensación de ligereza y desinflamación.' }
  ],
  'drenaje-linfatico-zona': [
    { title: 'Áreas Localizadas', text: 'Trabajamos de manera enfocada en la zona específica que más lo necesita, aplicando técnicas manuales suaves para un alivio focalizado.' },
    { title: 'Reducción de Inflamación', text: 'Ayudamos a reducir la inflamación y favorecemos la eliminación de toxinas, mejorando significativamente la circulación de la zona tratada.' },
    { title: 'Bienestar Inmediato', text: 'Disfruta de una sensación de ligereza instantánea, aliviando la hinchazón y mejorando el tono y apariencia natural de tu piel.' }
  ],
  'control-mantenimiento': [
    { title: 'Revisión Completa', text: 'Realizamos una evaluación y revisión exhaustiva cada 15 días para prevenir y tratar posibles molestias o alteraciones en sus etapas iniciales.' },
    { title: 'Limpieza y Cuidado', text: 'Acompañamos el control con una sesión de limpieza profunda, además del corte y limado profesional de uñas para garantizar su correcta forma y crecimiento.' },
    { title: 'Mantenimiento Preventivo', text: 'Servicio ideal para mantener tus pies en óptimas condiciones, especialmente diseñado si presentas tendencia a callosidades, durezas u otras afecciones leves.' }
  ]
}

// Merge all
Object.assign(PROCESS_STEPS_DATA, ADDITIONAL_STEPS)

/* ── Run migration ── */
async function migrateProcessSteps() {
  console.log('🚀 Starting process_steps migration...')
  let success = 0, fail = 0, skip = 0

  for (const [svcId, steps] of Object.entries(PROCESS_STEPS_DATA)) {
    try {
      await supabase.update('services', svcId, {
        process_steps: JSON.stringify(steps)
      })
      console.log(`✓ ${svcId}`)
      success++
    } catch (e) {
      // Try without the update (might not exist in DB)
      console.warn(`✗ ${svcId}: ${e.message}`)
      fail++
    }
  }

  console.log(`\n✅ Migration complete: ${success} updated, ${fail} failed, ${skip} skipped`)
  console.log('Remember to add process_steps column to Supabase if not exists:')
  console.log('ALTER TABLE services ADD COLUMN IF NOT EXISTS process_steps jsonb DEFAULT \'[]\'::jsonb;')
}

// To run: open browser console on admin page and call migrateProcessSteps()
console.log('Migration script loaded. Run migrateProcessSteps() to execute.')
