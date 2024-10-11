# Glossary Plugin Fixed Tooltip with Cache
A custom glossary plugin for WordPress, offering tooltip functionality, an archive page, caching, and more.

## Features
- **Custom Glossary Archive Page**:  
  A dedicated page with search functionality and alphabetical filtering, making it easy for users to find glossary terms.

- **Tooltips with Abbreviation Support**:  
  Displays brief descriptions in tooltips when hovering over terms, with the option to show full-form abbreviations in parentheses.

- **Caching for Improved Performance**:  
  Glossary terms are cached for faster loading and a smoother user experience.

- **Responsive Design**:  
  Works well on both desktop and mobile devices, ensuring accessibility for all users.

## Security Features
- **Input Sanitization**:  
  All user inputs, such as search queries and term entries, are sanitized to prevent malicious code injections.

- **Data Validation**:  
  Ensures that only valid data is stored in the database for tooltip text and glossary terms.

- **Nonce Verification**:  
  Uses nonce verification to secure form submissions, ensuring that requests are intentional and prevent CSRF attacks.

## Installation
1. **Download the Plugin**:  
   Download the plugin zip file from this repository.
   
2. **Upload to WordPress**:  
   Go to your WordPress dashboard, navigate to **Plugins > Add New**, and upload the zip file.
   
3. **Activate the Plugin**:  
   Activate the plugin through the **Plugins** menu in WordPress.

## Usage
- **Shortcode for Archive**:  
  Use the `[glossary_archive]` shortcode to display the glossary archive page on any page.
  
- **Adding Glossary Terms**:  
  Add terms in the WordPress dashboard under **Glossary**. Include descriptions and abbreviations as needed.
  
- **Automatic Tooltips**:  
  Tooltips will automatically appear for glossary terms in posts if the term matches the set criteria (e.g., capital letters).

## Known Bugs or Errors
- **Tooltip Overflow**:  
  On mobile devices, if the tooltip word is positioned too far to the right, it may become a long vertical text box instead of displaying correctly.

## License
This project is licensed under the GPLv3 License - see the [LICENSE](LICENSE) file for details.

## Contribution and Modification
You are free to use, modify, and distribute this plugin as you wish, as long as it remains open-source. Any modifications or derivative works must also be released under the same GPLv3 License. This ensures that the community can continue to benefit from and build upon this work.
