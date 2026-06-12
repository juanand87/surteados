<?php
/**
 * Chile regions and communes helper.
 * Source basis: Chile has 16 regions and 346 communes grouped by province/region.
 * See administrative division references in the implementation notes.
 */

function surteados_locations_dataset(): array
{
    return [
        ['id' => 15, 'name' => 'Arica y Parinacota', 'roman' => 'XV', 'communes' => ['Arica','Camarones','Putre','General Lagos']],
        ['id' => 1, 'name' => 'Tarapacá', 'roman' => 'I', 'communes' => ['Iquique','Alto Hospicio','Camiña','Colchane','Huara','Pica','Pozo Almonte']],
        ['id' => 2, 'name' => 'Antofagasta', 'roman' => 'II', 'communes' => ['Antofagasta','Mejillones','Sierra Gorda','Taltal','Calama','Ollagüe','San Pedro de Atacama','Tocopilla','María Elena']],
        ['id' => 3, 'name' => 'Atacama', 'roman' => 'III', 'communes' => ['Copiapó','Caldera','Tierra Amarilla','Chañaral','Diego de Almagro','Vallenar','Alto del Carmen','Freirina','Huasco']],
        ['id' => 4, 'name' => 'Coquimbo', 'roman' => 'IV', 'communes' => ['La Serena','Coquimbo','Andacollo','La Higuera','Paiguano','Vicuña','Illapel','Canela','Los Vilos','Salamanca','Ovalle','Combarbalá','Monte Patria','Punitaqui','Río Hurtado']],
        ['id' => 5, 'name' => 'Valparaíso', 'roman' => 'V', 'communes' => ['Valparaíso','Casablanca','Concón','Juan Fernández','Puchuncaví','Quintero','Viña del Mar','Isla de Pascua','Los Andes','Calle Larga','Rinconada','San Esteban','La Ligua','Cabildo','Papudo','Petorca','Zapallar','Quillota','Calera','Hijuelas','La Cruz','Nogales','San Antonio','Algarrobo','Cartagena','El Quisco','El Tabo','Santo Domingo','San Felipe','Catemu','Llaillay','Panquehue','Putaendo','Santa María','Quilpué','Limache','Olmué','Villa Alemana']],
        ['id' => 13, 'name' => 'Metropolitana de Santiago', 'roman' => 'RM', 'communes' => ['Santiago','Cerrillos','Cerro Navia','Conchalí','El Bosque','Estación Central','Huechuraba','Independencia','La Cisterna','La Florida','La Granja','La Pintana','La Reina','Las Condes','Lo Barnechea','Lo Espejo','Lo Prado','Macul','Maipú','Ñuñoa','Pedro Aguirre Cerda','Peñalolén','Providencia','Pudahuel','Quilicura','Quinta Normal','Recoleta','Renca','San Joaquín','San Miguel','San Ramón','Vitacura','Puente Alto','Pirque','San José de Maipo','Colina','Lampa','Tiltil','San Bernardo','Buin','Calera de Tango','Paine','Melipilla','Alhué','Curacaví','María Pinto','San Pedro','Talagante','El Monte','Isla de Maipo','Padre Hurtado','Peñaflor']],
        ['id' => 6, 'name' => "Libertador General Bernardo O'Higgins", 'roman' => 'VI', 'communes' => ['Rancagua','Codegua','Coinco','Coltauco','Doñihue','Graneros','Las Cabras','Machalí','Malloa','Mostazal','Olivar','Peumo','Pichidegua','Quinta de Tilcoco','Rengo','Requínoa','San Vicente','Pichilemu','La Estrella','Litueche','Marchihue','Navidad','Paredones','San Fernando','Chépica','Chimbarongo','Lolol','Nancagua','Palmilla','Peralillo','Placilla','Pumanque','Santa Cruz']],
        ['id' => 7, 'name' => 'Maule', 'roman' => 'VII', 'communes' => ['Talca','Constitución','Curepto','Empedrado','Maule','Pelarco','Pencahue','Río Claro','San Clemente','San Rafael','Cauquenes','Chanco','Pelluhue','Curicó','Hualañé','Licantén','Molina','Rauco','Romeral','Sagrada Familia','Teno','Vichuquén','Linares','Colbún','Longaví','Parral','Retiro','San Javier','Villa Alegre','Yerbas Buenas']],
        ['id' => 16, 'name' => 'Ñuble', 'roman' => 'XVI', 'communes' => ['Chillán','Bulnes','Chillán Viejo','El Carmen','Pemuco','Pinto','Quillón','San Ignacio','Yungay','Quirihue','Cobquecura','Coelemu','Ninhue','Portezuelo','Ránquil','Treguaco','San Carlos','Coihueco','Ñiquén','San Fabián','San Nicolás']],
        ['id' => 8, 'name' => 'Biobío', 'roman' => 'VIII', 'communes' => ['Concepción','Coronel','Chiguayante','Florida','Hualqui','Lota','Penco','San Pedro de la Paz','Santa Juana','Talcahuano','Tomé','Hualpén','Lebu','Arauco','Cañete','Contulmo','Curanilahue','Los Álamos','Tirúa','Los Ángeles','Antuco','Cabrero','Laja','Mulchén','Nacimiento','Negrete','Quilaco','Quilleco','San Rosendo','Santa Bárbara','Tucapel','Yumbel','Alto Biobío']],
        ['id' => 9, 'name' => 'La Araucanía', 'roman' => 'IX', 'communes' => ['Temuco','Carahue','Cunco','Curarrehue','Freire','Galvarino','Gorbea','Lautaro','Loncoche','Melipeuco','Nueva Imperial','Padre Las Casas','Perquenco','Pitrufquén','Pucón','Saavedra','Teodoro Schmidt','Toltén','Vilcún','Villarrica','Cholchol','Angol','Collipulli','Curacautín','Ercilla','Lonquimay','Los Sauces','Lumaco','Purén','Renaico','Traiguén','Victoria']],
        ['id' => 14, 'name' => 'Los Ríos', 'roman' => 'XIV', 'communes' => ['Valdivia','Corral','Lanco','Los Lagos','Máfil','Mariquina','Paillaco','Panguipulli','La Unión','Futrono','Lago Ranco','Río Bueno']],
        ['id' => 10, 'name' => 'Los Lagos', 'roman' => 'X', 'communes' => ['Puerto Montt','Calbuco','Cochamó','Fresia','Frutillar','Los Muermos','Llanquihue','Maullín','Puerto Varas','Castro','Ancud','Chonchi','Curaco de Vélez','Dalcahue','Puqueldón','Queilén','Quellón','Quemchi','Quinchao','Osorno','Puerto Octay','Purranque','Puyehue','Río Negro','San Juan de la Costa','San Pablo','Chaitén','Futaleufú','Hualaihué','Palena']],
        ['id' => 11, 'name' => 'Aysén del General Carlos Ibáñez del Campo', 'roman' => 'XI', 'communes' => ['Coyhaique','Lago Verde','Aysén','Cisnes','Guaitecas','Cochrane','O’Higgins','Tortel','Chile Chico','Río Ibáñez']],
        ['id' => 12, 'name' => 'Magallanes y de la Antártica Chilena', 'roman' => 'XII', 'communes' => ['Punta Arenas','Laguna Blanca','Río Verde','San Gregorio','Cabo de Hornos','Antártica','Porvenir','Primavera','Timaukel','Natales','Torres del Paine']],
    ];
}

function surteados_ensure_locations(PDO $pdo): void
{
    $pdo->exec("CREATE TABLE IF NOT EXISTS regions (
        id TINYINT UNSIGNED PRIMARY KEY,
        name VARCHAR(120) NOT NULL,
        roman VARCHAR(8) NULL,
        sort_order TINYINT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $pdo->exec("CREATE TABLE IF NOT EXISTS communes (
        id SMALLINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        region_id TINYINT UNSIGNED NOT NULL,
        name VARCHAR(120) NOT NULL,
        sort_order SMALLINT UNSIGNED NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        UNIQUE KEY uq_region_commune (region_id, name),
        INDEX idx_region (region_id),
        CONSTRAINT fk_communes_region FOREIGN KEY (region_id) REFERENCES regions(id) ON DELETE RESTRICT
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");

    $regionStmt = $pdo->prepare(
        "INSERT INTO regions (id, name, roman, sort_order) VALUES (?, ?, ?, ?)
         ON DUPLICATE KEY UPDATE name = VALUES(name), roman = VALUES(roman), sort_order = VALUES(sort_order)"
    );
    $communeStmt = $pdo->prepare(
        "INSERT INTO communes (region_id, name, sort_order) VALUES (?, ?, ?)
         ON DUPLICATE KEY UPDATE sort_order = VALUES(sort_order)"
    );

    foreach (surteados_locations_dataset() as $regionIndex => $region) {
        $regionStmt->execute([$region['id'], $region['name'], $region['roman'], $regionIndex + 1]);
        foreach ($region['communes'] as $communeIndex => $commune) {
            $communeStmt->execute([$region['id'], $commune, $communeIndex + 1]);
        }
    }

    if (!surteados_column_exists($pdo, 'tickets', 'buyer_commune_id')) {
        $pdo->exec('ALTER TABLE tickets ADD COLUMN buyer_commune_id SMALLINT UNSIGNED NULL AFTER buyer_comuna');
        $pdo->exec('ALTER TABLE tickets ADD INDEX idx_buyer_commune (buyer_commune_id)');
    }

    $pdo->exec(
        "UPDATE tickets t
            JOIN (
                SELECT name, MIN(id) AS id
                  FROM communes
                 GROUP BY name
                HAVING COUNT(*) = 1
            ) c ON c.name = t.buyer_comuna
           SET t.buyer_commune_id = c.id
         WHERE t.buyer_commune_id IS NULL
           AND t.buyer_comuna IS NOT NULL
           AND t.buyer_comuna <> ''"
    );
}

function surteados_column_exists(PDO $pdo, string $table, string $column): bool
{
    if (!preg_match('/^[a-zA-Z0-9_]+$/', $table)) {
        throw new InvalidArgumentException('Invalid table name');
    }
    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
           FROM information_schema.COLUMNS
          WHERE TABLE_SCHEMA = DATABASE()
            AND TABLE_NAME = ?
            AND COLUMN_NAME = ?'
    );
    $stmt->execute([$table, $column]);
    return (int)$stmt->fetchColumn() > 0;
}

function surteados_resolve_commune(PDO $pdo, mixed $communeId, string $fallbackName = ''): array
{
    surteados_ensure_locations($pdo);
    $id = (int)$communeId;
    if ($id > 0) {
        $stmt = $pdo->prepare(
            'SELECT c.id, c.name, c.region_id, r.name AS region_name
               FROM communes c
               JOIN regions r ON r.id = c.region_id
              WHERE c.id = ?'
        );
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        if ($row) {
            return [
                'id' => (int)$row['id'],
                'name' => (string)$row['name'],
                'region_id' => (int)$row['region_id'],
                'region_name' => (string)$row['region_name'],
            ];
        }
    }

    $fallbackName = trim($fallbackName);
    if ($fallbackName !== '') {
        $stmt = $pdo->prepare(
            'SELECT c.id, c.name, c.region_id, r.name AS region_name
               FROM communes c
               JOIN regions r ON r.id = c.region_id
              WHERE c.name = ?
              LIMIT 1'
        );
        $stmt->execute([$fallbackName]);
        $row = $stmt->fetch();
        if ($row) {
            return [
                'id' => (int)$row['id'],
                'name' => (string)$row['name'],
                'region_id' => (int)$row['region_id'],
                'region_name' => (string)$row['region_name'],
            ];
        }
    }

    json_error('Selecciona una comuna valida');
}
