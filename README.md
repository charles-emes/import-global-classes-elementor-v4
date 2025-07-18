Provides the means of loading CSS clases into Elementor Editor v4. Assumes that you do this at the very start of development before adding any global classes (because this process will remove them).

Requires Elementor to be installed and Editor v4 active. Check SELECT * FROM wp_postmeta WHERE meta_key = '_elementor_global_classes' returns 1 row.

Backup your database before clicking Update Elementor Global Classes.

Overwrites any existing gobal classes.

Most of these limitations are due to the current implementation of the Editor v4 interface: 

Supports single class declarations - no support for multiple classes like .toggle-icon .middle-bar{}
Supports properties with a single value - either a value like 10px or a variable like var(--space-s)
No support for properties with multiple values like {border:solid 1px #CCCCCC;} Use a variable {border:var(--border-s);}
No support for id classes like #mybtn, element classes like body or h1, h2, h3 , pseudo classes like ::before ::after
No support for @media queries or @container
