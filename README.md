# Simple Request (Simpest)
Makes simple requests to JSON API Endpoints using OAuth2 tokens.

## Installation and usage
This is a Drupal module. Install it like any other.

**To send a request, visit `/simpest/form/request`, and select the desired
options.**

## OAuth2 Client and post data
Add additional client configurations and data to post by adding yaml files to
the `/config/FormValues/Clients` and `/config/FormValues/PostData` directories
respectively.

## Motivation
I often need something to help troubleshoot Lightning API and it takes me WAY
too long to get everything configured each time. And Postman's new user
interface is too confusing for me.

The client configuration in the Clients directory matches the testing client
that ships with Lightning API. 