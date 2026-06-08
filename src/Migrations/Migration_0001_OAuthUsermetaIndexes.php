<?php
declare(strict_types=1);

/**
 * Indexes oauth_{provider}_user_id usermeta for fast provider-ID lookups.
 */

namespace WpOAuthConnect\Migrations;

final class Migration_0001_OAuthUsermetaIndexes
{
    public function up(\wpdb $wpdb): void
    {
        $table = $wpdb->usermeta;
        $index = 'woc_oauth_provider_user_id';

        $exists = $wpdb->get_var(
            $wpdb->prepare(
                'SHOW INDEX FROM `' . $table . '` WHERE Key_name = %s',
                $index,
            ),
        );

        if ($exists !== null) {
            return;
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
        $wpdb->query(
            'ALTER TABLE `' . $table . '` ADD INDEX `' . $index . '` (`meta_key`(32), `meta_value`(191))',
        );
    }
}