<!-- Rama de trabajo activa: feature-deposito-sucursal -->
<laravel-boost-guidelines>
=== foundation rules ===

# Laravel Boost Guidelines

The Laravel Boost guidelines are specifically curated by Laravel maintainers for this application. These guidelines should be followed closely to ensure the best experience when building Laravel applications.

## Foundational Context

This application is a Laravel application running on PHP 8.5. You are an expert with the Laravel ecosystem. Always use the APIs that match the installed major version of each package — do not assume a version.

Before relying on a package's API, confirm its installed version:

- PHP packages: run `composer show --direct` to list direct dependencies with versions, or `composer show <vendor/package>` for a single package.
- JS packages: check `package.json` for the installed versions.

## Skills Activation

This project has domain-specific skills available in `**/skills/**`. You MUST activate the relevant skill whenever you work in that domain—don't wait until you're stuck.

## Reglas Primordiales (Primordial)

- **Desglose Estricto de UI Mobile y Desktop**: Desglosar siempre las interfaces en módulos y directorios separados para Mobile y Desktop (por ejemplo: `resources/js/pages/auth/mobile/` y `resources/js/pages/auth/desktop/`, o subcarpetas dedicadas en componentes). Aunque puedan reutilizar componentes atómicos base y lógica compartida, las vistas, layouts y adaptaciones específicas de cada viewport deben mantenerse aisladas en sus respectivos módulos para evitar regresiones o corrupciones cruzadas entre Desktop y Mobile.
- **Máquinas de Estados Finitos (FSM) e Idempotencia en Flujos de Negocio**: Modelar siempre los flujos con ciclo de vida o cambios de estado (pedidos de clientes, órdenes de compra, facturación, etc.) como Máquinas de Estados Finitos (FSM) explícitas. Definir `ALLOWED_TRANSITIONS`, `canTransitionTo()` e `isTerminal()` en los modelos, envolver las mutaciones dentro de `DB::transaction()` con bloqueo pesimista (`lockForUpdate()`), garantizar que los estados terminales (`cancelado`, `entregado`, `recibido`) sean inmutables y no reincorporar/descontar stock repetidamente, y sincronizar reactivamente los modales de la UI (`bind:order` y `$effect`) con confirmaciones explícitas (`ConfirmActionModal`).
- **Uso Exclusivo de PNPM para Frontend y Dependencias**: Utilizar SIEMPRE y de manera obligatoria `pnpm` (ej. `pnpm run build`, `pnpm run dev`, `pnpm add`, `pnpm install`, `pnpm exec`) para instalar paquetes, compilar assets con Vite o correr scripts de frontend. NUNCA utilizar `npm` ni `yarn`.
- **Orquestación de Subagentes para Tareas de Gran Escala o Exploratorias**: En tareas donde se deba aplicar cambios transversales a múltiples secciones del sistema (como tipografía, diseño, refactors de UI, auditorías o tareas exploratorias), el agente principal debe actuar como orquestador, definiendo objetivos determinísticos y acotados para cada subagente para preservar la limpieza de la ventana de contexto, apoyándose en skills y herramientas MCP pertinentes.
- **Persistencia Obligatoria en Engram ante Compaction**: Siempre que se vaya a compactar la conversación o cerrar una sesión de trabajo, el agente debe persistir inmediatamente en Engram sus hallazgos, decisiones arquitectónicas y estado del plan de implementación mediante mem_save y mem_session_summary. Al iniciar una nueva ventana de contexto o reanudar sesión, debe recuperar proactivamente la información desde Engram antes de continuar.
- **Prohibición de Cards de Métricas / KPI Decorativas sin Solicitud Explícita**: Queda estrictamente prohibido agregar tarjetas de métricas, bloques Bento KPI o patrones decorativos (con líneas diagonales o placeholders vacíos) al inicio de paneles, dashboards o vistas de módulos a menos que el usuario lo solicite de forma explícita e inequívoca. Las vistas deben comenzar directamente con los controles operativos (searchbars, filtros, tablas o feeds de acción).
- **Estandarización de Endpoints y Rutas Visibles en Español**: Todo el código backend, controladores, modelos, métodos, variables y lógica interna se programan y mantienen en inglés conforme a los estándares de desarrollo. No obstante, las rutas web y endpoints visibles para el usuario final en el navegador DEBEN definirse obligatoriamente en español (kebab-case) para evitar el spanglish en la interfaz y barra de direcciones (ej: `/tenant/productos`, `/tenant/productos/buscar-deposito`, `/tenant/configuracion/depositos`, `/tenant/pedidos`, `/tenant/proveedores`, `/tenant/proveedores/ordenes`, `/tenant/clientes`, `/tenant/clientes/cuentas-corrientes`, `/tenant/stock/ingresos`, `/tenant/stock/egresos`, `/tenant/stock/kardex`, `/tenant/usuarios`, `/tenant/roles`, etc.).
- **Equilibrio y Simetría de Tamaños en Botones Adyacentes**: Cuando dos o más botones se ubiquen juntos o adyacentes (en barras de herramientas, cabeceras, grupos de acciones, modales o cards), DEBEN tener estrictamente la misma altura (`min-h-[*]`), padding vertical y tamaño de escala tipográfica (ej. ambos `size="sm"` con `min-h-[36px]` o `min-h-[38px]`, o ambos `min-h-[44px]` en cabeceras/modales/formularios). Queda terminantemente prohibido colocar botones de diferentes alturas o proporciones desalineadas contiguos entre sí (por ejemplo, un botón alto junto a uno diminuto).
- **Márgenes Internos Obligatorios en Diálogos y Modales**: Siempre se verifica y garantiza que todo dialog, modal o drawer (`DialogContent`, `DialogBody`, etc.) posea un margen y padding interno generoso (`p-5 sm:p-6 space-y-4`), asegurando que ningún formulario, campo de texto, switch, label o botón quede pegado a los bordes del diálogo, tanto en Desktop como en Mobile.
- **Separación Homogénea entre Cabecera/Título y Cuerpo del Módulo**: El título del módulo debe estar claramente separado del cuerpo operativo (filtros, searchbars, tablas o cards), respetando estrictamente una separación homogénea de 24px a 30px (`gap-6` o `mb-6`) en todos los módulos existentes y nuevos del sistema, evitando que la primera funcionalidad de la vista quede a la misma altura o pegada visualmente al encabezado.
- **Iteración Recursiva Completa sobre Módulos e Involucrados**: Al analizar, diseñar, auditar o refactorizar cualquier módulo del sistema, es obligatorio iterar recursivamente sobre la totalidad de elementos y actores que lo integran: vistas principales (Desktop y Mobile), diálogos y modales asociados (`Dialog`, `Modal`, `Drawer`), pestañas o vistas secundarias de creación y edición (`Create.svelte`, `Edit.svelte`), componentes embebidos y configuraciones particulares de usuario o comercio. Ningún elemento hijo o periférico puede quedar fuera de los criterios, contrastes y estándares de diseño del sistema (campos de texto, botones, tipografía, touch targets).
- **Integridad y Prohibición de Eliminación en AGENTS.md**: Queda estrictamente prohibido eliminar, truncar o modificar líneas o reglas existentes en este archivo `AGENTS.md` a no ser que el usuario dé la orden explícita e inequívoca de hacerlo. Toda nueva directiva debe anexarse preservando íntegramente las reglas anteriores.
- **Prohibición de Fuga de Información del Sistema en Alertas y UI**: Queda terminantemente prohibido hacer mención de nombres de archivos internos (como `.env`, archivos de configuración, controllers, nombres de variables de entorno o rutas del servidor), tablas de base de datos o arquitectura interna en alertas, modales, diálogos, toasts o cualquier elemento visual del sistema. Todo mensaje de cara al usuario final debe formularse exclusivamente en términos funcionales y comprensibles de negocio (ej. "El servicio de cobros no se encuentra disponible temporalmente. Contacte al soporte técnico."), salvaguardando la seguridad y profesionalismo de la aplicación sin importar el rol técnico o no técnico de quien la utilice.
- **Rigor y Exhaustividad en Planes de Implementación**: Al diagramar, diseñar o proponer planes de implementación, refactors o nuevas funcionalidades, es obligatorio elaborar planes exhaustivos, explicativos y altamente detallados (apoyándose en diagramas de secuencia/flujo Mermaid, justificaciones arquitectónicas, contratos de datos, especificaciones de FSM y matrices de testing TDD). Queda estrictamente prohibido entregar planes superficiales, simplificados o que omitan edge cases de concurrencia, consistencia transaccional o adaptaciones entre viewports (Mobile/Desktop).
- **Metodología de Planes de Refactorización y Accesibilidad UI/UX Mobile**: Toda iniciativa de refinamiento o adaptación para dispositivos móviles debe concebirse no solo para dimensiones estándar de pantalla (360px–390px), sino fundamentalmente para resistir el escalado de fuentes del sistema operativo (Dynamic Type / A11y Scaling de iOS y Android). El diseño del plan debe estructurarse mediante un análisis exhaustivo y sistemático de seis pilares: (1) Contenedores de búsqueda y acciones primarias apilables verticalmente (`flex-col sm:flex-row`) al 100% de ancho sin truncamiento de etiquetas ni botones; (2) Desglose de resultados de autocompletado y menús desplegables (`itemSnippet`) con protección multilínea (`line-clamp-2 break-words`) y cifras monetarias/métricas fijadas a la derecha con `shrink-0 text-right`; (3) Tarjetas de ítems seleccionados con envolvimiento elástico (`flex-wrap items-center justify-between gap-3`) garantizando que selectores y steppers táctiles nunca expulsen el subtotal fuera de la pantalla; (4) Formularios de ítems de proveedores adaptados a cuadrículas modulares (`grid grid-cols-2 gap-3`) para costo y cantidad, con subtotales en filas inferiores dedicadas; (5) Pie de modales (*footers*) con montos numéricos flexibles (`flex-wrap break-all`) y botones de acción distribuidos simétricamente en grilla móvil (`grid grid-cols-2 gap-2.5 w-full sm:flex sm:w-auto`) con áreas táctiles accesibles (mínimo `min-h-[44px]` y `whitespace-nowrap`); y (6) Cabeceras y bloques Bento resilientes con `items-start`, `min-w-0 flex-1` en títulos con `line-clamp-2 break-words`, y `shrink-0` estricto en controles de cierre o iconos. Todo plan debe documentarse de forma preliminar en un artefacto interactivo con diagramas Mermaid antes de delegar la codificación a subagentes especializados.
- **Prohibición Estricta de Párrafos Descriptivos Redundantes en Títulos**: Queda terminantemente prohibido agregar párrafos descriptivos, subtítulos innecesarios o textos secundarios explicativos al lado o debajo de los títulos principales en componentes, paneles, tarjetas, diálogos (`Dialog`), drawers o vistas, a menos que el usuario lo solicite de forma explícita e inequívoca. La interfaz debe priorizar la concisión, la elegancia visual y la densidad de información limpia sin explicaciones superfluas que sobrecarguen la pantalla.
- **Prohibición Estricta de Redundancia Textual en Iconos, Signos y Unidades**: Queda estrictamente prohibido duplicar o redundar en los textos de interfaz (etiquetas `<Label>`, botones, opciones de select o tooltips) información que ya esté expresada visualmente mediante iconos contiguos, signos matemáticos, o prefijos/sufijos fijos en campos de formulario.
  1. *Signos con Iconos*: Prohibido añadir `(+)` o `(-)` cuando el elemento contenga iconos adyacentes de suma/resta (`<PlusCircle>`, `<MinusCircle>`) o cuando la acción sea intrínsecamente aditiva o sustractiva (ej. usar `Descuento` en lugar de `Descuento (-)`, `Recargo` en lugar de `Recargo (+)`).
  2. *Unidades con Iconos o Sufijos*: Prohibido añadir `(%)` o `($)` a etiquetas cuando el campo ya cuente con adornments nativos (`%`, `$`), iconos representativos (`<Percent>`, `<DollarSign>`), o cuando el término sea explícito (ej. usar `Porcentaje a aplicar` o `Valor` en lugar de `Porcentaje a aplicar (%)`; `Nuevo precio` en lugar de `Nuevo precio ($)`).
  3. *Verificación de Microcopy Previa a Conclusión*: Toda tarea de diseño, refactor o modificación de vistas y modales debe incluir una auditoría obligatoria de concisión tipográfica antes de darse por completada, erradicando placeholders redundantes (ej. `placeholder="%"` con sufijo `%`) y textos sobre-explicativos.
- **Desacoplamiento Estricto y Estandarización de Cabeceras de Diálogos (Dialogs / Modales)**: Toda cabecera de modal o diálogo (`DialogHeader`) debe desacoplar obligatoriamente el contenedor del icono del contenedor del título en elementos hermanos independientes (`shrink-0` para el icono y `min-w-0 flex-1` para el título/textos) separados por un borde divisorio tenue (`border-b border-border/60 pb-3`).
  1. *Clasificación Estructural*:
     - **Tipo 1 (Icono y título únicamente)**: El contenedor del título se estructura con `class="min-w-0 flex-1 flex items-center"`, logrando una alineación geométrica y visual exacta con el centro horizontal del icono.
     - **Tipo 2 (Icono, título y subtítulo/leyenda)**: El contenedor del texto se estructura con `class="min-w-0 flex-1 flex flex-col justify-center"`, alojando el título principal (`DialogTitle`) y una leyenda o subtítulo complementario (`text-xs text-muted-foreground mt-0.5` o `mt-1`), preservando la simetría de altura con el contenedor del icono.
  2. *Dimensiones y Márgenes Mobile (< sm)*: La disposición, márgenes y proporciones del icono deben replicar estrictamente el patrón canónico del modal de detalle de pedido (`OrderDetailModal`): contenedor de icono en `size-10` (40px) [o máximo 36px `size-9`], con icono interno en `size-5` (20px), y márgenes perimetrales holgados (`p-4 pb-3.5` o padding de diálogo generoso), asegurando que el icono nunca quede pegado, comprimido o aplastado contra los bordes exteriores ni la línea divisoria. Título en `text-lg font-bold tracking-tight text-foreground`.
  3. *Dimensiones y Márgenes Desktop (≥ sm)*: El contenedor del icono escala a 48px (`sm:size-12`), con icono interno en 24px (`sm:size-6`) y un margen perimetral exacto de 3px (`sm:p-[3px]` o `sm:pb-[3px]`). Título en `sm:text-xl font-bold tracking-tight text-foreground` centrado con el eje del contenedor del icono.
  4. *Prevención de Recorte Tipográfico (Descendentes)*: En Desktop y Mobile, los títulos deben contar con suficiente altura de línea y margen vertical (`leading-snug` o micro-padding vertical) para evitar terminantemente que letras con trazo descendente (`g`, `j`, `p`, `q`, `y`) queden truncadas o comprimidas contra los bordes o elementos vecinos.
  5. *Microcopy y Accesibilidad*: Queda prohibido añadir párrafos explicativos superfluos. Si se requiere proveer contexto accesible para lectores de pantalla, la descripción debe encapsularse obligatoriamente en `<DialogDescription class="sr-only">`.
- **Blindaje de Compatibilidad SQL Dual (PostgreSQL / SQLite) y Smoke Tests de Instanciación de Módulos**: Queda terminantemente prohibido utilizar comillas dobles (`"..."`) para valores o cadenas de texto literales en cualquier sentencia o expresión SQL (`DB::raw`, `selectRaw`, `whereRaw`, vistas SQL o migraciones). En el estándar SQL y en PostgreSQL (motor de base de datos de producción), las comillas dobles están reservadas exclusivamente para identificadores de columnas y tablas; utilizarlas para textos literales provoca de inmediato el fallo crítico `SQLSTATE[42703]: Undefined column: no existe la columna «...»`. Todo texto o valor literal en SQL debe encerrarse obligatoriamente con comillas simples (`'...'`) o resolverse a nivel de aplicación en PHP mediante operadores de coalescencia (`??`). Asimismo, ante cada nuevo módulo o refactorización, es obligatorio construir pruebas de integración y humo (*Smoke Tests* de instanciación) que ejecuten peticiones HTTP reales autenticadas contra los endpoints principales (`/tenant/...`) con datos precargados en base de datos para verificar que controladores, servicios y consultas agregadas se instancien de punta a punta sin excepciones 500 ni incompatibilidades entre motores de bases de datos.





# Directrices de Diseño UI/UX y Sistema de Interfaces

## 1. Principios de Apple (Human Interface Guidelines)

### A. Deferencia y Claridad

- **El contenido es el rey:** La interfaz gráfica no debe competir con los datos del usuario. Evita decoraciones superfluas (bordes gruesos, gradientes estridentes o patrones de fondo complejos).
- **Tipografía escalonada:** Usa una única familia tipográfica variando peso y tamaño para establecer niveles:
  - Título principal: `font-bold` / `text-2xl` o `text-xl`
  - Subtítulos/Secciones: `font-semibold` / `text-base`
  - Cuerpo regular: `font-normal` / `text-sm`
  - Metadatos/Captions: `font-normal` / `text-xs` con contraste reducido (`text-muted-foreground`).

### B. Profundidad y Superficies

- **Capas semánticas en lugar de líneas duras:** Separa niveles de profundidad con variaciones tonales de fondo (`bg-background` -> `bg-card` -> `bg-popover`) y desenfoque sutil (`backdrop-blur-md` en headers/navbars).
- **Sombras suaves y direccionales:** Usa sombras difusas y de baja opacidad (`shadow-sm`, `shadow-md`), nunca sombras negras y opacas de radio corto.

---

## 2. Principios de Google (Material Design 3)

### A. Roles de Color y Contenedores Tonales

- **Separación de roles cromáticos:**
  - `Primary`: Acción principal de la pantalla o estado activo crítico.
  - `Secondary / Muted`: Filtros, chips secundarios, fondos auxiliares.
  - `Surface / Container`: Bloques de contenido (`Card`, `Dialog`, `Sidebar`). Usa niveles tonales (`surface-1`, `surface-2`) para indicar elevación sin depender únicamente de sombras.
  - `Destructive / Error`: Reservado exclusivamente para acciones irreversibles o fallos críticos.

### B. Ergonomía y Touch Targets

- **Áreas mínimas de interacción:** Todo elemento interactivo (botones, iconos, checkboxes, filas clickeables) debe tener un área táctil mínima de **44×44 px** (o `min-h-[40px]` con padding clickeable), incluso si el icono visual mide 16px.
- **Estados interactivos completos:** Todo componente interactivo DEBE incluir estilos explícitos para: `hover`, `focus-visible` (anillo de foco accesible sin outline nativo roto), `active` y `disabled`.

---

## 3. Principios Enterprise (Shopify Polaris, IBM Carbon)

### A. Gestión de Densidad y Carga Cognitiva

- **Divulgación Progresiva (*Progressive Disclosure*):**
  - Muestra en primera instancia solo los 3 a 5 datos clave para la toma de decisiones.
  - Oculta detalles secundarios o acciones infrecuentes bajo menús desplegables (`...`), acordeones o vistas de detalle dedicadas.
- **Tablas vs. Cards:**
  - Usa **Tablas** cuando el usuario deba comparar, ordenar o escanear horizontalmente múltiples registros estructurados.
  - Usa **Cards** cuando cada elemento represente una entidad autónoma con métricas o estados diferenciados.

### B. Microcopy y Prevención de Errores

- **Textos de acción explícitos:** Los botones deben describir la acción exacta mediante verbos en infinitivo ("Guardar cambios", "Exportar CSV", "Eliminar producto"), NUNCA etiquetas ambiguas como "Aceptar", "OK" o "Continuar".
- **Diálogos de Confirmación:**
  - *Título:* Pregunta directa sobre la acción (ej. "¿Eliminar este registro?").
  - *Cuerpo:* Explica la consecuencia exacta en 1 o 2 oraciones (ej. "Esta acción no se puede deshacer y se perderán todos los datos vinculados").
  - *Botones:* Botón destructivo a la derecha/final con etiqueta roja sólida ("Eliminar") + Botón de cancelación neutral ("Cancelar").

---

## 4. Leyes Psicológicas y Heurísticas de UX

- **Ley de Proximidad (Gestalt):** El espacio entre elementos de un mismo grupo (`gap-1.5` o `gap-2`) debe ser significativamente menor que el espacio que separa un grupo del siguiente (`mb-6` o `gap-6`).
- **Ley de Hick:** Reduce la cantidad de opciones concurrentes. Si un formulario tiene más de 6-8 campos, evalúa agruparlo en pasos secuenciales (*steppers*) o secciones colapsables lógicas.
- **Ley de Fitts:** Los botones primarios deben posicionarse en los extremos naturales del flujo de lectura (abajo a la derecha en modales, arriba a la derecha en cabeceras de página).
- **Feedback Inmediato:** Toda mutación o acción asíncrona debe reflejar:
  - Estado de carga (`isPending` / `disabled` + spinner sutil).
  - Estado vacío (*Empty State*) con ilustración o icono sutil, explicación de ausencia de datos y botón de acción para crearlo.
  - Notificación de éxito o error (*Toast* o banner discreto).

---

## 5. Reglas de Validación Rápida (DO / DON'T)

| Aspecto               | DO (Hacer)                                                                                     | DON'T (Evitar)                                                               |
| :-------------------- | :--------------------------------------------------------------------------------------------- | :--------------------------------------------------------------------------- |
| **Bordes**      | Usar separaciones con fondos sutiles (`bg-muted/50`) o bordes tenues (`border-border/60`). | Enmarcar cada elemento con bordes negros o contrastados.                     |
| **Colores**     | Mantener una paleta neutra con un único color de acento para acciones clave.                  | Usar múltiples colores primarios (azul, verde, púrpura) en la misma vista. |
| **Botones**     | Máximo 1 botón con estilo sólido primario por bloque o modal.                               | Poner 3 botones primarios sólidos juntos compitiendo por atención.         |
| **Cards**       | Padding generoso y consistente (`p-5` o `p-6`), títulos claros y métricas destacadas.    | Llenar la tarjeta con 10 campos de texto plano y etiquetas en negrita.       |
| **Alineación** | Alinear números a la derecha en tablas y textos a la izquierda.                               | Centrar textos largos o números en columnas comparativas.                   |

---

## 6. Estándares Tipográficos, Escala Modular y Tratamiento Numérico

El agente debe aplicar una jerarquía tipográfica rigurosa, limpia y matemática, evitando tamaños arbitrarios o desalineaciones en datos densos.

### A. Escala Modular de Tamaños y Pesos

- **Page Titles / Headers de Vista:** `text-2xl` (24px) a `text-3xl` (30px) | `font-bold` o `font-semibold` | `tracking-tight` | `leading-tight`.
- **Card Titles / Subsecciones:** `text-base` (16px) a `text-lg` (18px) | `font-semibold` | `tracking-normal` | `leading-snug`.
- **Body / Inputs / Celdas de Tabla:** `text-sm` (14px) | `font-normal` | `leading-normal` (estándar óptimo para densidad en dashboards).
- **Labels de Formularios:** `text-sm` (14px) | `font-medium` | `text-foreground`.
- **Metadata / Badges / Captions:** `text-xs` (12px) | `font-medium` | `tracking-wide` | `text-muted-foreground`.

### B. Reglas de Tracking (Letter-Spacing) y Leading (Line-Height)

- **Tracking Inverso:**
  - En textos pequeños (`text-xs`, badges, etiquetas en mayúsculas): aplicar tracking positivo (`tracking-wide` o `tracking-wider`) para evitar empastes.
  - En títulos grandes (`text-xl` hacia arriba): aplicar tracking negativo (`tracking-tight`) para un acabado compacto y moderno.
- **Leading Proporcional:**
  - Títulos: `leading-none` o `leading-tight` (evita separaciones excesivas entre líneas de encabezado).
  - Párrafos y descripciones: `leading-relaxed` (1.5x a 1.6x) para legibilidad continua.

### C. Tratamiento de Datos Numéricos y Tabulares

- **Cifras Tabulares Obligatorias (`tabular-nums`):**
  - Todo dato numérico dentro de **tablas, métricas de dashboards, precios, timestamps, temporizadores y contadores** DEBE incluir la clase `tabular-nums` (`font-variant-numeric: tabular-nums`).
  - Previene que los números "bailen" horizontalmente cuando cambian los valores o al alinearse en columnas.
- **Alineación de Columnas:**
  - Texto descriptivo y nombres: alineación a la izquierda (`text-left`).
  - Cantidades, montos, porcentajes y fechas: alineación a la derecha (`text-right`).
  - Estados, badges booleanos y acciones: centrados (`text-center`).

### D. Restricciones Tipográficas (DO / DON'T)

| Aspecto                           | DO (Hacer)                                                                                                                            | DON'T (Evitar)                                                                                          |
| :-------------------------------- | :------------------------------------------------------------------------------------------------------------------------------------ | :------------------------------------------------------------------------------------------------------ |
| **Tamaños Arbitrarios**    | Ceñirse a la escala (`text-xs`, `text-sm`, `text-base`, `text-lg`, `text-xl`, `text-2xl`).                               | Usar valores arbitrarios en CSS (`text-[13px]`, `text-[17px]`).                                     |
| **Variedad de Fuentes**     | Usar una sola familia sans-serif neutra/neo-grotesca (`Inter`, `Geist`, `system-ui`).                                           | Mezclar 2 o más familias tipográficas distintas en la misma interfaz.                                 |
| **Contraste de Jerarquía** | Crear jerarquía variando el color (`text-foreground` vs `text-muted-foreground`) y el peso (`font-medium` vs `font-normal`). | Cambiar drásticamente de tamaño de fuente para cada línea de texto secundaria.                       |
| **Mayúsculas sostenidas**  | Usar`uppercase` solo en badges pequeños, códigos SKU o tags de 1 palabra con `text-xs font-semibold tracking-wider`.            | Usar textos largos o botones completos en MAYÚSCULAS sostenidas.                                       |
| **Longitud de Línea**      | Limitar el ancho máximo de párrafos a 60-75 caracteres (`max-w-prose` o `max-w-lg`).                                            | Dejar párrafos de texto explicativo estirarse a lo largo de todo el ancho de la pantalla (`w-full`). |



---

## 7. Colorimetría y Uso Semántico (Regla 60-30-10)

El agente debe utilizar la paleta de colores registrada (text, background, primary, secondary, accent) siguiendo estrictamente el modelo de distribución 60-30-10 para mantener la jerarquía visual y evitar la fatiga cognitiva del usuario.

### A. Distribución del Color (60-30-10)

- **60% - Superficies y Fondos (`background` y neutrales):**
  - Es el color dominante del sistema.
  - El fondo principal del sistema usa `bg-background-50` (light) o `bg-background-950` (dark).
  - Las tarjetas (`Cards`), modales y contenedores elevados usan `bg-background-100` o `bg-background-200` para generar contraste de superficie sutil.
- **30% - Contenido y Secundarios (`text` y `secondary`):**
  - Los textos principales usan `text-900` o `text-950`. Textos descriptivos usan `text-500` o `text-600`.
  - Botones secundarios, bordes y elementos de apoyo usan la escala `--secondary-*`.
- **10% - Acentos y Acciones Primarias (`primary` y `accent`):**
  - Reservado EXCLUSIVAMENTE para lo que requiere atención inmediata.
  - Botones principales (Guardar, Confirmar), estados activos, checkboxes seleccionados y enlaces importantes usan `--primary-500` o `--primary-600`.
  - Detalles visuales sutiles (iconos decorativos, badges) usan `--accent-*`.

### B. Reglas de Opacidad y Estados Interactivos

En lugar de crear nuevos colores para estados, utiliza modificadores de opacidad (alpha) sobre el color base:

- **Hover en botones primarios:** Reducir opacidad al 90% (`hover:bg-primary-500/90`).
- **Estados deshabilitados (Disabled):** Usar colores secundarios/fondo con baja opacidad y texto atenuado (`bg-background-200 text-text-400 cursor-not-allowed`).
- **Fondos de modales/Overlays:** Fondo oscuro con alta transparencia y desenfoque (`bg-text-950/50 backdrop-blur-sm`).
- **Componentes "Ghost" o "Outline":** Fondo transparente con texto/icono primario, y al hacer hover usar un fondo primario con opacidad del 10% (`hover:bg-primary-500/10 text-primary-600`).

### C. Restricciones de Color (DO / DON'T)

| Aspecto                     | DO (Hacer)                                                                                                      | DON'T (Evitar)                                                                   |
| :-------------------------- | :-------------------------------------------------------------------------------------------------------------- | :------------------------------------------------------------------------------- |
| **Fondos**            | Usar los tonos más claros de`background` (50, 100) para superficies grandes.                                 | Usar un color`primary` para el fondo de toda una sección o pantalla.          |
| **Texto sobre Color** | Asegurar contraste: usar texto muy claro (`text-50`) sobre fondos oscuros (`primary-600`).                  | Poner texto gris medio (`text-400`) sobre fondos primarios o secundarios.      |
| **Bordes**            | Usar bordes muy sutiles (`border-background-300` o `border-secondary-200`).                                 | Usar negro puro, gris oscuro o el color primario al 100% para bordes divisorios. |
| **Sombras (Shadows)** | Teñir ligeramente la sombra con el color de fondo para que se integre (`shadow-sm shadow-background-900/5`). | Usar sombras negras por defecto (`rgba(0,0,0, 0.5)`).                          |

### D. Campos de Texto y Formularios (Dark Mode)
- **Contraste de Inputs:** Los campos de texto NUNCA deben compartir el mismo color exacto del fondo del contenedor. Deben usar un tono más claro (`bg-background-900`) o un fondo translúcido (`bg-black/20`) para crear contraste de profundidad.
- **Bordes Visibles:** Definir los límites del input con bordes de baja opacidad (`border-white/10` o `border-background-700`).
- **Estados Focus:** Todo input debe reaccionar al foco ocultando el outline nativo y aplicando un borde del color primario (`focus:outline-none focus:border-primary-500`).
- **Estados Disabled:** Los inputs deshabilitados deben distinguirse claramente reduciendo la opacidad del texto (`text-text-600`), quitando el fondo sólido (`bg-transparent`) y usando bordes punteados o ausentes (`border-dashed`).

## 8. Accesibilidad y Soporte para Fuentes Grandes (A11y)

El sistema debe soportar el escalado de fuentes del sistema operativo sin que la UI se desborde, los textos se corten o los botones se superpongan. El agente debe aplicar las siguientes reglas deterministas:

- **Contenedores Flexibles:** En layouts horizontales (`flex`), utilizar siempre `flex-wrap gap-x` en lugar de forzar a los elementos en una sola línea si contienen texto dinámico.
- **Evitar Alturas Fijas:** NUNCA utilizar clases de altura estática (ej. `h-10`, `h-16`) en componentes que contengan texto (botones, tarjetas, *list items*). Utilizar exclusivamente alturas mínimas (`min-h-10 px-4 py-2`) para permitir la expansión vertical.
- **Protección de Elementos de Acción:** Los iconos, checkboxes y botones de acción dentro de filas deben llevar siempre la clase `shrink-0` para evitar que se aplasten cuando el texto adyacente crezca.
- **Control de Desbordamiento:** Utilizar `line-clamp-2` o `line-clamp-3` en títulos o descripciones largas para limitar su crecimiento vertical. NUNCA truncar (`truncate`) valores críticos como precios, métricas, cantidades o códigos de error.

## Conventions

- You must follow all existing code conventions used in this application. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods. For example, `isRegisteredForDiscounts`, not `discount()`.
- Check for existing components to reuse before writing a new one.

## Verification Scripts

- Do not create verification scripts or tinker when tests cover that functionality and prove they work. Unit and feature tests are more important.

## Application Structure & Architecture

- Stick to existing directory structure; don't create new base folders without approval.
- Do not change the application's dependencies without approval.

## Frontend Bundling

- If the user doesn't see a frontend change reflected in the UI, it could mean they need to run `pnpm run build`, `pnpm run dev`, or `composer run dev`. Ask them.

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.

=== boost rules ===

# Laravel Boost

## Tools

- Laravel Boost is an MCP server with tools designed specifically for this application. Prefer Boost tools over manual alternatives like shell commands or file reads.
- Use `database-query` to run read-only queries against the database instead of writing raw SQL in tinker.
- Use `database-schema` to inspect table structure before writing migrations or models.
- Use `get-absolute-url` to resolve the correct scheme, domain, and port for project URLs. Always use this before sharing a URL with the user.
- Use `browser-logs` to read browser logs, errors, and exceptions. Only recent logs are useful, ignore old entries.

## Searching Documentation (IMPORTANT)

- Always use `search-docs` before making code changes. Do not skip this step. It returns version-specific docs based on installed packages automatically.
- Pass a `packages` array to scope results when you know which packages are relevant.
- Use multiple broad, topic-based queries: `['rate limiting', 'routing rate limiting', 'routing']`. Expect the most relevant results first.
- Do not add package names to queries because package info is already shared. Use `test resource table`, not `filament 4 test resource table`.

### Search Syntax

1. Use words for auto-stemmed AND logic: `rate limit` matches both "rate" AND "limit".
2. Use `"quoted phrases"` for exact position matching: `"infinite scroll"` requires adjacent words in order.
3. Combine words and phrases for mixed queries: `middleware "rate limit"`.
4. Use multiple queries for OR logic: `queries=["authentication", "middleware"]`.

## Project Rules

- This project contains committed, area-grouped rules in `.ai/rules` when that directory exists (settled decisions, non-obvious traps, standing constraints). Framework and package guidelines that only apply to specific paths (testing, frontend, components) also live there, under `.ai/rules/boost` — this is not just recorded decisions, it is load-bearing guidance you have not seen inline. Before you enter plan mode or create/edit any file, you MUST first: open @.ai/rules/index.md (it maps file globs to rule files), read every rule file whose globs cover the path(s) in scope, and run `grep -rin 'keyword' .ai/rules` to catch what a path match alone misses. Do not write code until you have read and are following every matching rule. If `.ai/rules` does not exist, continue without it.
- Record durable rules with `record-rule` so the next agent or teammate inherits them instead of working them out again. Pass a `glob` (e.g. `app/Http/Controllers/**`), a short `title`, and a few-line `note`. Always use `record-rule`, never your native memory or notes tool — native memory is personal and session-scoped; only `.ai/rules` is shared with the team and persists in the repo.

## Artisan

- Run Artisan commands directly via the command line (e.g., `php artisan route:list`). Use `php artisan list` to discover available commands and `php artisan [command] --help` to check parameters.
- Inspect routes with `php artisan route:list`. Filter with: `--method=GET`, `--name=users`, `--path=api`, `--except-vendor`, `--only-vendor`.
- Read configuration values using dot notation: `php artisan config:show app.name`, `php artisan config:show database.default`. Or read config files directly from the `config/` directory.

## Tinker

- Execute PHP in app context for debugging and testing code. Do not create models without user approval, prefer tests with factories instead. Prefer existing Artisan commands over custom tinker code.
- Always use single quotes to prevent shell expansion: `php artisan tinker --execute 'Your::code();'`
  - Double quotes for PHP strings inside: `php artisan tinker --execute 'User::where("active", true)->count();'`

=== php rules ===

# PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion: `public function __construct(public GitHub $github) { }`. Do not leave empty zero-parameter `__construct()` methods unless the constructor is private.
- Use explicit return type declarations and type hints for all method parameters: `function isAccessible(User $user, ?string $path = null): bool`
- Use TitleCase for Enum keys: `FavoritePerson`, `BestLake`, `Monthly`.
- Prefer PHPDoc blocks over inline comments. Only add inline comments for exceptionally complex logic.
- Use array shape type definitions in PHPDoc blocks.

=== deployments rules ===

# Deployment

- Laravel can be deployed using [Laravel Cloud](https://cloud.laravel.com/), which is the fastest way to deploy and scale production Laravel applications.

=== herd rules ===

# Laravel Herd

- The application is served by Laravel Herd at `https?://[kebab-case-project-dir].test`. Use the `get-absolute-url` tool to generate valid URLs. Never run commands to serve the site. It is always available.
- Use the `herd` CLI to manage services, PHP versions, and sites (e.g. `herd sites`, `herd services:start <service>`, `herd php:list`). Run `herd list` to discover all available commands.

=== tests rules ===

# Test Enforcement

- Every change must be programmatically tested. Write a new test or update an existing test, then run the affected tests to make sure they pass.
- Run the minimum number of tests needed to ensure code quality and speed. Use `php artisan test --compact` with a specific filename or filter.

=== inertia-laravel/core rules ===

# Inertia

- Inertia creates fully client-side rendered SPAs without modern SPA complexity, leveraging existing server-side patterns.
- Components live in `resources/js/pages` (unless specified in `vite.config.js`). Use `Inertia::render()` for server-side routing instead of Blade views.
- ALWAYS use `search-docs` tool for version-specific Inertia documentation and updated code examples.
- IMPORTANT: Activate `inertia-svelte-development` when working with Inertia Svelte client-side patterns.

# Inertia v3

- Use all Inertia features from v1, v2, and v3. Check the documentation before making changes to ensure the correct approach.
- New v3 features: standalone HTTP requests (`useHttp` hook), optimistic updates with automatic rollback, layout props (`useLayoutProps` hook), instant visits, simplified SSR via `@inertiajs/vite` plugin, custom exception handling for error pages.
- Carried over from v2: deferred props, infinite scroll, merging props, polling, prefetching, once props, flash data.
- When using deferred props, add an empty state with a pulsing or animated skeleton.
- Axios has been removed. Use the built-in XHR client with interceptors, or install Axios separately if needed.
- `Inertia::lazy()` / `LazyProp` has been removed. Use `Inertia::optional()` instead.
- Prop types (`Inertia::optional()`, `Inertia::defer()`, `Inertia::merge()`) work inside nested arrays with dot-notation paths.
- SSR works automatically in Vite dev mode with `@inertiajs/vite` - no separate Node.js server needed during development.
- Event renames: `invalid` is now `httpException`, `exception` is now `networkError`.
- `router.cancel()` replaced by `router.cancelAll()`.
- The `future` configuration namespace has been removed - all v2 future options are now always enabled.

=== laravel/core rules ===

# Do Things the Laravel Way

- Use `php artisan make:` commands to create new files (i.e. migrations, controllers, models, etc.). You can list available Artisan commands using `php artisan list` and check their parameters with `php artisan [command] --help`.
- If you're creating a generic PHP class, use `php artisan make:class`.
- Pass `--no-interaction` to all Artisan commands to ensure they work without user input. You should also pass the correct `--options` to ensure correct behavior.

### Model Creation

- When creating new models, create useful factories and seeders for them too. Ask the user if they need any other things, using `php artisan make:model --help` to check the available options.

## APIs & Eloquent Resources

- For APIs, default to using Eloquent API Resources and API versioning unless existing API routes do not, then you should follow existing application convention.

## URL Generation

- When generating links to other pages, prefer named routes and the `route()` function.

## Testing

- When creating models for tests, use the factories for the models. Check if the factory has custom states that can be used before manually setting up the model.
- Faker: Use methods such as `$this->faker->word()` or `fake()->randomDigit()`. Follow existing conventions whether to use `$this->faker` or `fake()`.
- When creating tests, make use of `php artisan make:test [options] {name}` to create a feature test, and pass `--unit` to create a unit test. Most tests should be feature tests.

## Vite Error

- If you receive an "Illuminate\Foundation\ViteException: Unable to locate file in Vite manifest" error, you can run `pnpm run build` or ask the user to run `pnpm run dev` or `composer run dev`.

=== wayfinder/core rules ===

# Laravel Wayfinder

Use Wayfinder to generate TypeScript functions for Laravel routes. Import from `@/actions/` (controllers) or `@/routes/` (named routes).

=== pint/core rules ===

# Laravel Pint Code Formatter

- If you have modified any PHP files, you must run `vendor/bin/pint --dirty --format agent` before finalizing changes to ensure your code matches the project's expected style.
- Do not run `vendor/bin/pint --test --format agent`, simply run `vendor/bin/pint --format agent` to fix any formatting issues.

=== pest/core rules ===

## Pest

- This project uses Pest for testing. Create tests: `php artisan make:test --pest {name}`.
- The `{name}` argument should not include the test suite directory. Use `php artisan make:test --pest SomeFeatureTest` instead of `php artisan make:test --pest Feature/SomeFeatureTest`.
- Run tests: `php artisan test --compact` or filter: `php artisan test --compact --filter=testName`.
- Do NOT delete tests without approval.

=== inertia-svelte/core rules ===

# Inertia + Svelte

- IMPORTANT: Activate `inertia-svelte-development` when working with Inertia Svelte client-side patterns.

</laravel-boost-guidelines>
