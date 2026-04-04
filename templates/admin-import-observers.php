<?php
if ( ! current_user_can( 'manage_options' ) ) {
    wp_die( 'Insufficient permissions' );
}
?>
<div class="wrap">
    <h1><?php esc_html_e( 'Import Observers', 'pollmonitor' ); ?></h1>
    <p><?php esc_html_e( 'Upload a CSV exported from Excel to create or update observer accounts in bulk.', 'pollmonitor' ); ?></p>

    <?php if ( ! empty( $import_error ) ) : ?>
        <div class="notice notice-error"><p><?php echo esc_html( $import_error ); ?></p></div>
    <?php endif; ?>

    <?php if ( ! empty( $import_result ) ) : ?>
        <div class="notice notice-success">
            <p>
                <?php
                echo esc_html(
                    sprintf(
                        'Import complete. Created: %1$d, Updated: %2$d, Errors: %3$d.',
                        (int) $import_result['created'],
                        (int) $import_result['updated'],
                        (int) $import_result['errors']
                    )
                );
                ?>
            </p>
        </div>
    <?php endif; ?>

    <form method="post" enctype="multipart/form-data">
        <?php wp_nonce_field( 'pollmonitor_import_observers' ); ?>
        <table class="form-table" role="presentation">
            <tr>
                <th scope="row"><label for="pollmonitor_observer_csv"><?php esc_html_e( 'Observer CSV File', 'pollmonitor' ); ?></label></th>
                <td>
                    <input type="file" name="pollmonitor_observer_csv" id="pollmonitor_observer_csv" accept=".csv,text/csv" required>
                    <p class="description"><?php esc_html_e( 'Required column: email. Optional columns: full_name, first_name, last_name, username, phone, observer_id, assigned_station_ids, password.', 'pollmonitor' ); ?></p>
                    <p class="description"><?php esc_html_e( 'Use assigned_station_ids as comma-separated poll_station post IDs, for example: 123,456,789.', 'pollmonitor' ); ?></p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button type="submit" name="pollmonitor_import_observers" class="button button-primary">
                <?php esc_html_e( 'Import Observers', 'pollmonitor' ); ?>
            </button>
        </p>
    </form>

    <h2><?php esc_html_e( 'Sample CSV Header', 'pollmonitor' ); ?></h2>
    <pre>email,full_name,username,phone,observer_id,assigned_station_ids,password</pre>

    <h2><?php esc_html_e( 'Example Row', 'pollmonitor' ); ?></h2>
    <pre>observer1@example.com,Amina Yusuf,amina.yusuf,08030000000,PM-OBS-01001,"123,456",</pre>

    <?php if ( ! empty( $import_result['rows'] ) ) : ?>
        <h2><?php esc_html_e( 'Import Results', 'pollmonitor' ); ?></h2>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th><?php esc_html_e( 'Row', 'pollmonitor' ); ?></th>
                    <th><?php esc_html_e( 'Status', 'pollmonitor' ); ?></th>
                    <th><?php esc_html_e( 'Email', 'pollmonitor' ); ?></th>
                    <th><?php esc_html_e( 'Username', 'pollmonitor' ); ?></th>
                    <th><?php esc_html_e( 'Observer ID', 'pollmonitor' ); ?></th>
                    <th><?php esc_html_e( 'Assigned Stations', 'pollmonitor' ); ?></th>
                    <th><?php esc_html_e( 'Generated Password', 'pollmonitor' ); ?></th>
                    <th><?php esc_html_e( 'Message', 'pollmonitor' ); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $import_result['rows'] as $row_result ) : ?>
                    <tr>
                        <td><?php echo esc_html( (string) $row_result['row_number'] ); ?></td>
                        <td><?php echo esc_html( strtoupper( $row_result['status'] ) ); ?></td>
                        <td><?php echo esc_html( $row_result['email'] ); ?></td>
                        <td><?php echo esc_html( $row_result['username'] ); ?></td>
                        <td><?php echo esc_html( $row_result['observer_id'] ); ?></td>
                        <td><?php echo esc_html( implode( ', ', $row_result['assigned_station_ids'] ) ); ?></td>
                        <td><code><?php echo esc_html( $row_result['password'] ); ?></code></td>
                        <td><?php echo esc_html( $row_result['message'] ); ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</div>
