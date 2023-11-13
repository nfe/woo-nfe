module.exports = grunt => {
	require('load-grunt-tasks')(grunt);
	grunt.loadNpmTasks( 'grunt-wp-i18n' );
	grunt.initConfig({
		pkg: grunt.file.readJSON('package.json'),

		// Check textdomain errors.
		checktextdomain: {
			options:{
				text_domain: 'woo-nfe',
				keywords: [
					'__:1,2d',
					'_e:1,2d',
					'_x:1,2c,3d',
					'esc_html__:1,2d',
					'esc_html_e:1,2d',
					'esc_html_x:1,2c,3d',
					'esc_attr__:1,2d',
					'esc_attr_e:1,2d',
					'esc_attr_x:1,2c,3d',
					'_ex:1,2c,3d',
					'_n:1,2,4d',
					'_nx:1,2,4c,5d',
					'_n_noop:1,2,3d',
					'_nx_noop:1,2,3c,4d'
				]
			},
			files: {
				src:  [
					'**/*.php', // Include all files
					'!apigen/**', // Exclude apigen/
					'!node_modules/**', // Exclude node_modules/
				],
				expand: true
			}
		},
		makepot: {
			target: {
				options: {
					type: 'wp-plugin'
				}
			}
		}
	});
};
