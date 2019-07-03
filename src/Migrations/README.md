DB actions when checking out other branches:

UP (from branch **dev** to this one):

`php bin/console doctrine:migrations:execute --up 20190619180140`
`php bin/console doctrine:migrations:execute --up 20190622112701`
`php bin/console doctrine:migrations:execute --up 20190624102453`


DOWN (from this branch to **dev**):

`php bin/console doctrine:migrations:execute --down 20190624102453`
`php bin/console doctrine:migrations:execute --down 20190622112701`
`php bin/console doctrine:migrations:execute --down 20190619180140`


When this feature is done and working properly, we should probably merge those migrations into one file. 