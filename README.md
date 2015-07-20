# SMF Gravity Forms S3 Upload (based on Paulsen GForms S3 Uploads)

This plugin moves files submitted through Gravity Forms to S3 after form submission.  The files can be placed into a specified "folder" path

## Installation 

1. Define constants in the plugin file in order to connect to Amazon S3 :

   `define( 'GFORM_S3_BUCKET', '<bucket name>' );`

   `define( 'GFORM_S3_PATH_PREFIX', '<folder name with trailing slash or empty for no folder>' );`

   `define( 'AWS_ACCESS_KEY_ID', '<aws access key>' );`

   `define( 'AWS_SECRET_ACCESS_KEY', '<aws secret key>' );`

2. Install and activate like a typical WP plugin.


## How it works (so far)
- Automatically gets applied to file upload fields (on all forms).  There is currently no way around this – all files submitted through a Gravity Form go to S3.
- Supports multiple forms, fields
- The actual field value is changed to the new network path on S3 (if successful) or the web server location (if upload to S3 failed).

## To Do

- Add admin area to select which forms and fields to use
- Add option to apply to any upload field
- Integrate with the wp-amazon-web-services plugin
- Verify that the file was successfully uploaded
