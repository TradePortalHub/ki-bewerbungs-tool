<?php
// templates/apply-template.php
defined( 'ABSPATH' ) || exit;
get_header();

// 1) Aktuelle job_id aus der URL holen
$job_id = sanitize_text_field( get_query_var( 'job_id' ) );

// 2) Alle JSON‑Dateien in public/companies einlesen
$dir = trailingslashit( plugin_dir_path( __FILE__ ) . '../public/companies' );
$jobs = [];
if ( is_dir( $dir ) ) {
    foreach ( glob( $dir . '*.json' ) as $file ) {
        $data = json_decode( file_get_contents( $file ), true );
        if ( ! is_array( $data ) ) {
            continue;
        }
        // Firmenbasis‑Daten
        $company = [
            'companyName'        => $data['companyName']        ?? '',
            'companyDescription' => $data['companyDescription'] ?? '',
            'companyLogo'        => $data['companyLogo']        ?? '',
            'companyAddress'     => $data['companyAddress']     ?? '',
            'companyContactPerson'=> $data['companyContactPerson'] ?? '',
            'companyWebsite'     => $data['companyWebsite']     ?? '',
            'companyContactDetails'=> $data['companyContactDetails'] ?? '',
        ];
        // 2a) postings‑Array
        if ( isset( $data['postings'] ) && is_array( $data['postings'] ) ) {
            foreach ( $data['postings'] as $p ) {
                $jobs[] = array_merge( $company, $p );
            }
        }
        // 2b) jobs‑Array (fallback)
        elseif ( isset( $data['jobs'] ) && is_array( $data['jobs'] ) ) {
            foreach ( $data['jobs'] as $p ) {
                $jobs[] = array_merge( $company, $p );
            }
        }
        // 2c) Einzel‑Job‑Objekt
        elseif ( isset( $data['id'], $data['title'] ) ) {
            $jobs[] = array_merge( $company, $data );
        }
    }
}

// 3) Gewünschte Ausschreibung finden
$current = null;
foreach ( $jobs as $job ) {
    if ( isset( $job['id'] ) && $job['id'] === $job_id ) {
        $current = $job;
        break;
    }
}

// 4) Wenn nicht gefunden, Fehlermeldung
if ( ! $current ) {
    echo '<div class="kibt-error p-6 bg-red-100 text-red-800 rounded">';
    echo '<p>Stelle "' . esc_html( $job_id ) . '" nicht gefunden.</p>';
    echo '</div>';
    get_footer();
    return;
}

// 5) Firmen‑Header ausgeben
?>
<div class="kibt-company p-6 bg-gray-50 rounded mb-6">
    <?php if ( $current['companyLogo'] ): ?>
        <img src="<?php echo esc_url( $current['companyLogo'] ); ?>"
             alt="<?php echo esc_attr( $current['companyName'] ); ?> Logo"
             class="mb-4" style="max-height:60px;">
    <?php endif; ?>
    <h1 class="text-2xl font-bold mb-2"><?php echo esc_html( $current['companyName'] ); ?></h1>
    <p class="mb-2"><?php echo esc_html( $current['companyDescription'] ); ?></p>
    <ul class="text-sm text-gray-700 mb-4">
        <?php if ( $current['companyAddress'] ): ?>
        <li><strong>Adresse:</strong> <?php echo esc_html( $current['companyAddress'] ); ?></li>
        <?php endif;?>
        <?php if ( $current['companyContactPerson'] ): ?>
        <li><strong>Ansprechpartner:</strong> <?php echo esc_html( $current['companyContactPerson'] ); ?></li>
        <?php endif;?>
        <?php if ( $current['companyWebsite'] ): ?>
        <li><strong>Web:</strong>
            <a href="<?php echo esc_url( $current['companyWebsite'] ); ?>" target="_blank">
                <?php echo esc_html( $current['companyWebsite'] ); ?>
            </a>
        </li>
        <?php endif;?>
        <?php if ( $current['companyContactDetails'] ): ?>
        <li><strong>Kontakt:</strong> <?php echo esc_html( $current['companyContactDetails'] ); ?></li>
        <?php endif;?>
    </ul>
</div>

<?php
// 6) Stellen‑Details ausgeben
?>
<div class="kibt-job-detail p-6 bg-white rounded shadow mb-6">
    <h2 class="text-xl font-semibold mb-2"><?php echo esc_html( $current['title'] ); ?></h2>
    <p class="italic text-gray-600 mb-4">"<?php echo esc_html( $current['announcement'] ); ?>"</p>
    <p class="mb-4"><?php echo esc_html( $current['description'] ); ?></p>

    <?php if ( ! empty( $current['questions'] ) ): ?>
    <div class="mb-4">
        <h3 class="font-medium">Fragen:</h3>
        <ul class="list-disc list-inside">
            <?php foreach ( $current['questions'] as $q ): ?>
                <li><?php echo esc_html( $q ); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if ( ! empty( $current['fileUploads'] ) ): ?>
    <div class="mb-4">
        <h3 class="font-medium">Dateien hochladen:</h3>
        <ul class="list-disc list-inside">
            <?php foreach ( $current['fileUploads'] as $f ): ?>
                <li>
                    <?php echo esc_html( $f['label'] ?? $f['name'] ); ?>
                    <?php if ( ! empty( $f['required'] ) ): ?>
                        <strong>(Pflicht)</strong>
                    <?php endif;?>
                </li>
            <?php endforeach;?>
        </ul>
    </div>
    <?php endif; ?>

    <?php if ( ! empty( $current['informationRequested'] ) ): ?>
    <div class="mb-4">
        <h3 class="font-medium">Zusätzliche Infos:</h3>
        <ul class="list-disc list-inside">
            <?php foreach ( $current['informationRequested'] as $info ): ?>
                <li><?php echo esc_html( $info ); ?></li>
            <?php endforeach;?>
        </ul>
    </div>
    <?php endif; ?>
</div>

<?php
// 7) Mount‑Point für dein React‑ChatWidget
?>
<div id="kibt-app" data-job-id="<?php echo esc_attr( $job_id ); ?>"></div>

<?php
get_footer();
