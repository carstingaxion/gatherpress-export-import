# Gatherpress | Export & Import

> [!NOTE]
> Is this real, GatherPress has no mechanism in place to allow the use of the default export and import tools?
>
> https://github.com/GatherPress/gatherpress/issues/650

The code in this repo tries, to solve this issue.

[Test it using WordPress' playground
](https://playground.wordpress.net/builder/builder.html#{%22$schema%22:%22https://playground.wordpress.net/blueprint-schema.json%22,%22landingPage%22:%22https://playground.wordpress.net/events%22,%22preferredVersions%22:{%22php%22:%228.2%22,%22wp%22:%226.5%22},%22phpExtensionBundles%22:[%22kitchen-sink%22],%22features%22:{%22networking%22:true},%22steps%22:[{%22step%22:%22setSiteOptions%22,%22options%22:{%22blogname%22:%22GatherPress%22,%22blogdescription%22:%22Powering%20Communities%20with%20WordPress.%22,%22users_can_register%22:1}},{%22step%22:%22login%22,%22username%22:%22admin%22,%22password%22:%22password%22},{%22step%22:%22installPlugin%22,%22pluginZipFile%22:{%22resource%22:%22url%22,%22url%22:%22https://raw.githubusercontent.com/carstingaxion/gatherpress-demo-data/main/gatherpress-0.28.0.zip%22},%22options%22:{%22activate%22:true}},{%22step%22:%22installPlugin%22,%22pluginZipFile%22:{%22resource%22:%22url%22,%22url%22:%22https://raw.githubusercontent.com/carstingaxion/gatherpress-export-import/main/gatherpress-export-import-main.zip%22},%22options%22:{%22activate%22:true}},{%22step%22:%22importFile%22,%22file%22:{%22resource%22:%22url%22,%22url%22:%22https://raw.githubusercontent.com/carstingaxion/gatherpress-demo-data/main/GatherPress-demo-data-2024.xml%22}}]})
