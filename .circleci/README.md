### Configuring CircleCi Project

1. Create Github Auth Token:
- Login to github Under user who has rights to commit
- Go to [token page](https://github.com/settings/tokens)
- Click on Generate New Token
- Click on checkbox near 'repo Full control of private repositories'
- Click Generate token

2. Create project from repository in CicleCi
3. Go to project's settings -> Environment Variables, click on Add Variable
4. Create variable with name GITHUB_AUTH_TOKEN and value - previously generated token
5. Run build